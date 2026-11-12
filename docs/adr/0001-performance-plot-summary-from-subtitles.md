# ADR 0001: Performance plot summary from Jellyfin subtitles

- **Status:** Accepted
- **Date:** 2026-11-11

## Context

When a recording is added to Jellyfin, Jellyfin generates Estonian speech-to-text subtitles for it automatically. That takes hours to days after the video appears. The subtitles contain typos and occasional logic errors from inaccurate transcription, but they are the only text record of what happened on stage.

We want a short plot summary of each recorded performance, plus a few thematic tags ("armastus", "kalapüük", "laul") that make similar shows findable:

- both shown on the performance page in plaan
- the summary appended to the episode description in Jellyfin, and the tags added to the episode's Jellyfin tags

The summary is generated once per recording, and only after its subtitles exist.

## Decision

### Where the summary lives

The summary is stored on `performance_recordings`, because subtitles exist per recording. The table gains five columns:

| Column | Purpose |
|---|---|
| `plot_summary` | The summary text. Technicians can edit it by hand. |
| `plot_tags` | JSON list of thematic tags written with the summary. Lowercase, unique, at most 8, each at most 40 characters. Not editable by hand. |
| `plot_summary_generated_at` | Marks that generation has run. Clearing the text by hand does not cause it to run again. |
| `plot_summary_abandoned_at` | Marks that we stopped waiting (see the 14-day limit below). |
| `linked_at` | When the current link was made. The 14-day window counts from this. |

Changing the recording URL means a different video. It clears the summary, its tags and all its markers, the same way it already resets `synced_at` and `sync_error`. Restoring a cleared link with the same URL keeps the summary.

### Writing to Jellyfin

The summary reaches Jellyfin through plaan's own description. `JellyfinEpisodeMetadata::overview()` appends the summary after the existing lines, separated by a blank line. The existing `SyncPerformanceToJellyfin` listener pushes it.

The tags go the same way: `JellyfinEpisodeMetadata::tags()` adds `plot_tags` to the tags plaan already writes (the `plaan` marker, format, performer, venue, season and technical-plan tags), with duplicates removed.

The alternative was appending to whatever Overview Jellyfin already holds. That was rejected: the sync is one-way and rewrites the whole Overview on every push, so a summary added only in Jellyfin would disappear the next time the recording is re-linked.

### Flow

1. **Daily command.** The scheduled `performances:summarise-recordings` command selects recordings that:
   - are linked to an item;
   - belong to a live performance;
   - have no summary and no markers.

   It marks a recording abandoned once 14 days have passed since `linked_at`. For every other candidate it dispatches `PerformancePlotSummaryRequested`.
2. **Queued listener.** The listener is `SummarisePerformancePlot`, which is unique per recording. It:
   1. Lists the item's subtitle streams with `GET /Items?ids={id}&fields=MediaSources`. This is the query form that works on both Jellyfin 10.x and 12.x.
   2. Picks the first text subtitle stream whose Language is `est` or `et`. If there is none, it does nothing and tomorrow's run tries again.
   3. Downloads that stream as SRT from `GET /Videos/{id}/{mediaSourceId}/Subtitles/{index}/Stream.srt`.
3. **Summary.** `PerformancePlotSummariser` reduces the SRT to plain dialogue lines. It sends them to Claude through the existing `anthropic-ai/sdk` setup (`CachesClaudeMessages`), together with context about the performance:
   - format name and description
   - who was on stage, which helps correct misheard names

   The system prompt explains that the transcript is noisy speech-to-text, and that the summary is for a viewer about to watch the show: present tense, the story as a whole rather than a scene-by-scene account. The answer uses a JSON schema with `usable`, `summary`, `tags` and `reason`, so Claude can say a transcript is unusable instead of inventing a plot. An unusable transcript marks the recording abandoned.

   Tags are 3–8 Estonian themes, settings, eras or forms. Names of performers or the format, the date and the venue are left out, because plaan already tags those. The service normalises whatever comes back (lowercase, trimmed, unique, capped in count and length).
4. **Push.** A saved summary fires `PerformancePlotSummaryWritten`. `SyncPerformanceToJellyfin` now listens for it too, and pushes the updated Overview and tags.
5. **Recording email.** The "recording available" email to plan authors and performers includes the summary and tags. `NotifyRecordingAvailable` therefore no longer sends on `PerformanceRecordingLinked`. It waits for the first `PerformancePlotSummaryWritten` that carries text, which can come from the model or from a technician typing one by hand. An AI-written summary comes with a "koostatud tehisaru abil" disclaimer.
   - A recording given up on fires `PerformancePlotSummaryAbandoned`, from the daily command's 14-day cutoff or from an unusable transcript, and the email then goes out without a summary.
   - If summaries cannot be written at all (no Jellyfin or Claude configured), the email still goes out at link time.
   - `announced_at` still limits the email to once per recording, so a regenerated or edited summary sends nothing new.

### Manual control

- **Editing.** Technicians can edit the summary in the performance edit dialog. The edit goes through the same path as the recording link. A changed summary is pushed to Jellyfin.
- **Tags are not edited by hand.** Editing the summary leaves the tags as they are. A technician who wants different tags asks for a fresh summary.
- **Regenerating.** Technicians can ask for a fresh summary. This clears the markers, restarts the 14-day window, and runs the listener right away without the answer cache. The old text and tags stay visible until they are replaced.
- **Who sees it.** Everyone who can open the performance page sees the summary and its tags.

## Consequences

- One Jellyfin read per waiting recording per day. This is cheap, and nothing is retried more often than daily.
- The subtitle generator has to make Jellyfin notice the new subtitle file, for example through an item refresh. plaan does not trigger library scans.
- A recording whose subtitles never appear stops being checked after 14 days. A technician can start it again with "regenerate".
- The migration sets `linked_at` on existing recordings to the deploy time, not to when they were linked. Older recordings, which most likely already have subtitles, get the same 14-day window as new ones; the first daily run summarises them all.
- An unescaped straight quote (`"`) inside the summary ends the JSON string as the structured-output grammar reads it. The rest of the summary is silently dropped, but the answer is still valid JSON, because it carries on with `tags`. We saw this twice in testing. The prompt asks for Estonian quotes („…“), and as a fallback `PerformancePlotSummariser::isWhole()` rejects a summary that doesn't end in sentence punctuation. A rejected answer is not cached (`askClaude(..., keep:)`) and the job throws, so the queue retry asks Claude afresh. Answers that aren't JSON at all are handled the same way (seen once in seven real calls), and their opening is logged for diagnosis.
- A long performance can produce a transcript of tens of thousands of tokens. This fits comfortably in one request to the configured model.
- Editing a performance does not push to Jellyfin, except when the recording link or the summary changes. This is how the sync has always worked.
