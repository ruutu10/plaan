# ADR 0001: Performance plot summary from Jellyfin subtitles

- **Status:** Accepted
- **Date:** 2026-11-11

## Context

When a recording is added to Jellyfin, Jellyfin generates Estonian speech-to-text subtitles for it automatically. That takes hours to days after the video appears. The subtitles contain typos and occasional logic errors from inaccurate transcription, but they are the only text record of what happened on stage.

We want a short plot summary of each recorded performance:

- shown on the performance page in plaan
- appended to the episode description in Jellyfin

The summary is generated once per recording, and only after its subtitles exist.

## Decision

### Where the summary lives

The summary is stored on `performance_recordings`, because subtitles exist per recording. The table gains four columns:

| Column | Purpose |
|---|---|
| `plot_summary` | The summary text. Technicians can edit it by hand. |
| `plot_summary_generated_at` | Marks that generation has run. Clearing the text by hand does not cause it to run again. |
| `plot_summary_abandoned_at` | Marks that we stopped waiting (see the 30-day limit below). |
| `linked_at` | When the current link was made. The 30-day window counts from this. |

Changing the recording URL means a different video. It clears the summary and all its markers, the same way it already resets `synced_at` and `sync_error`. Restoring a cleared link with the same URL keeps the summary.

### Writing to Jellyfin

The summary reaches Jellyfin through plaan's own description. `JellyfinEpisodeMetadata::overview()` appends `Sisukokkuvõte:` and the summary after the existing lines. The existing `SyncPerformanceToJellyfin` listener pushes it.

The alternative was appending to whatever Overview Jellyfin already holds. That was rejected: the sync is one-way and rewrites the whole Overview on every push, so a summary added only in Jellyfin would disappear the next time the recording is re-linked.

### Flow

1. **Daily command.** The scheduled `performances:summarise-recordings` command selects recordings that:
   - are linked to an item;
   - belong to a live performance;
   - have no summary and no markers.

   It marks a recording abandoned once 30 days have passed since `linked_at`. For every other candidate it dispatches `PerformancePlotSummaryRequested`.
2. **Queued listener.** The listener is `SummarisePerformancePlot`, which is unique per recording. It:
   1. Lists the item's subtitle streams with `GET /Items?ids={id}&fields=MediaSources`. This is the query form that works on both Jellyfin 10.x and 12.x.
   2. Picks the first text subtitle stream whose Language is `est` or `et`. If there is none, it does nothing and tomorrow's run tries again.
   3. Downloads that stream as SRT from `GET /Videos/{id}/{mediaSourceId}/Subtitles/{index}/Stream.srt`.
3. **Summary.** `PerformancePlotSummariser` reduces the SRT to plain dialogue lines. It sends them to Claude through the existing `anthropic-ai/sdk` setup (`CachesClaudeMessages`), together with context about the performance:
   - format name and description
   - who was on stage, which helps correct misheard names

   The system prompt explains that the transcript is noisy speech-to-text. The answer uses a JSON schema with `usable`, `summary` and `reason`, so Claude can say a transcript is unusable instead of inventing a plot. An unusable transcript marks the recording abandoned.
4. **Push.** A saved summary fires `PerformancePlotSummaryWritten`. `SyncPerformanceToJellyfin` now listens for it too, and pushes the updated Overview.

### Manual control

- **Editing.** Technicians can edit the summary in the performance edit dialog. The edit goes through the same path as the recording link. A changed summary is pushed to Jellyfin.
- **Regenerating.** Technicians can ask for a fresh summary. This clears the markers, restarts the 30-day window, and runs the listener right away without the answer cache. The old text stays visible until it is replaced.
- **Who sees it.** Everyone who can open the performance page sees the summary.

## Consequences

- One Jellyfin read per waiting recording per day. This is cheap, and nothing is retried more often than daily.
- The subtitle generator has to make Jellyfin notice the new subtitle file, for example through an item refresh. plaan does not trigger library scans.
- A recording whose subtitles never appear stops being checked after 30 days. A technician can start it again with "regenerate".
- The migration sets `linked_at` on existing recordings to the deploy time, not to when they were linked. Older recordings, which most likely already have subtitles, get the same 30-day window as new ones; the first daily run summarises them all.
- A long performance can produce a transcript of tens of thousands of tokens. This fits comfortably in one request to the configured model.
- Editing a performance does not push to Jellyfin, except when the recording link or the summary changes. This is how the sync has always worked.
