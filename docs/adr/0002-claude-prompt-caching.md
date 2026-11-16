# ADR 0002: Claude prompt caching

- **Status:** Accepted
- **Date:** 2026-11-16

## Context

plaan calls Claude from four services. All of them go through `CachesClaudeMessages::askClaude()`:

| Service | System prompt | How it is called |
|---|---|---|
| `PlankaPerformanceExtractor` | ~24 KB, plus the house's team and format lists in the user message | Once per card in `planka:import`, one card after another |
| `TechnicalPlanReviewer` | ~18 KB | From the editor's "AI review" button, often clicked again after a few minutes of edits, and on submit |
| `PerformancePlotSummariser` | ~7 KB | One episode after another in the backfill and summarise commands, and from a queued listener |
| `TechnicalPlanCriticalFindings` | ~4 KB | Once per submitted plan, straight after the review |

`askClaude()` already has an **answer cache**. It keeps the model's answer for three weeks, keyed on a hash of the whole request. That only helps when a request repeats exactly, character for character. It does nothing for the common case: the system prompt, and in the Planka import the house's lists, are the same on every call, and only the card, transcript or plan differs. That shared part was billed at the full input price every time. It also showed up in every response's time to first token.

Anthropic's **prompt cache** covers that case. A request marks a point in its prompt with `cache_control`. The API keeps everything up to that point for five minutes, and refreshes the five minutes on every hit. The next request that starts with exactly the same bytes reads that prefix back instead of processing it again. On Sonnet 5, writing the cache costs 1.25× the base input price and reading it costs 0.1×. Prompts shorter than 1,024 tokens are not cached, and the API does not report an error when it skips them.

Nothing recorded the token usage of a call, so there was no way to tell whether any of this happened.

## Decision

### Explicit breakpoints, not automatic caching

The API also offers automatic caching: one top-level `cacheControl`, which the API places on the last block of the request. That doesn't fit here. In every call the last block is the unique part: the card, the transcript or the plan. The cache is only written at breakpoints, so the shared system prompt would never get an entry of its own. Each call would pay the 1.25× write price and nothing would ever read it back.

Instead, `CachesClaudeMessages::cachedPromptBlock()` builds a text block with `cacheControl: {type: ephemeral}`. Each service puts that block at the end of the part that doesn't change between calls.

### Where the breakpoints are

- **`PlankaPerformanceExtractor`, two breakpoints.**
  - The system prompt.
  - A new first block of the user message holding the teams and formats lists (`buildHousePrompt()`). The card follows in a block of its own (`buildCardPrompt()`). The lists were already loaded once per run so that each card is asked the same question. That same choice now makes them a stable prefix.
  - The two breakpoints are separate because the two parts change at different rates. The system prompt changes on deploy. The lists change when a format or team is added. A changed list still leaves the system prompt cached.
- **`TechnicalPlanReviewer`:** the system prompt.
- **`PerformancePlotSummariser`:** the system prompt.
- **`TechnicalPlanCriticalFindings`: none.** It runs once per submit, and nothing reuses its prefix within five minutes. It would pay for a cache write and never read it back. It also can't share the reviewer's entry. The two passes have different system prompts, and the system prompt comes before the plan, so their prefixes differ from the very start.

The variable parts (plan JSON, transcripts, card text) stay plain strings after the last breakpoint.

### TTL

Every breakpoint uses the default five-minute TTL. The import and backfill commands make calls seconds apart, and a performer revising a plan usually asks again within minutes. Each hit resets the five minutes at no cost. The one-hour TTL costs 2× to write, which isn't worth it at plaan's traffic.

### Logging

`askClaude()` logs one `Claude usage` info line per call to the API. It holds the service class, the model, `input_tokens`, `cache_creation_input_tokens`, `cache_read_input_tokens` and `output_tokens`. Answers served from the answer cache make no API call, so they log nothing here.

### The two caches together

The answer cache stays as it is, in front of the prompt cache. An exact repeat is still answered from the application cache without any call to the API. Everything else now goes to the API with its shared prefix cached.

## Consequences

- **Cheaper Planka imports.** In a run, every card after the first reads the system prompt and the lists from the cache. With roughly 8k shared tokens, a 40-card run's input drops from about $0.64 to about $0.08 on Sonnet 5. Responses also start sooner. The token count is estimated from file size, not measured. The `Claude usage` lines give the real figure.
- **The answer cache misses once after deploy.** `system` and the Planka user message changed shape, so every request hashes to a new key, and each question goes to the API one more time.
- **Anything that varies must stay after the breakpoints.** A timestamp, a request id or an unsorted list in a Blade system prompt or in `buildHousePrompt()` would change the prefix on every call. Every call would then pay to write the cache and none would read it. The extractor test asserts that two consecutive cards send exactly the same cached blocks. A Blade prompt that later gains a variable part would break caching without any error. The way to notice is `cache_read_input_tokens` staying at 0 in the logs.
- **The order of the lists matters.** `Format::namesByKey()` and `Team::namesById()` both sort by name, so the same formats and teams always produce the same block. Dropping that `orderBy` would leave the order to the database, and cards could then miss the cache.
- **Caches are per model.** Changing `services.anthropic.model` starts every prefix cold, as it does the answer cache.
- **The minimum length applies to the whole prefix.** The three cached system prompts are all well above Sonnet 5's 1,024 tokens. A prompt trimmed below the minimum would just stop being cached, with no error.
- **`TechnicalPlanReviewer` builds its client on first use.** It used to build it in the constructor. This matches the other services, and lets tests hand it a client with a canned transport (`TechnicalPlanReviewerTest`).
- **Planka tests read prompt text through `sentSystemPrompt()` and `sentUserPrompt()`** in `Tests\Concerns\AnswersAsTheExtractionModel`. `system` and the user message are now lists of blocks rather than strings.
