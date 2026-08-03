# Plaan — user manual

Plaan is the technical-planning system of the Ruutu10 improv theatre. Performing
groups describe what their show needs from the light and sound desk; the
technical crew collects those descriptions, confirms them, and runs the evening
from them.

This document describes how the system behaves for the people using it: what you
can do, in what order, what happens automatically, and where the limits are.

The interface itself is in Estonian. Estonian labels are given in brackets where
they help you find the screen.

---

## 1. Who uses the system

| Person | What they do |
| --- | --- |
| **Performer** | Fills in the technical plan for a show they are about to play. Usually arrives by e-mail link and never needs to learn the rest of the system. |
| **Group member / group admin** | Belongs to one or more performing groups (teams). Keeps the group's shows, performance dates and membership straight. |
| **Technician** (`Tehnik` role) | Runs the shows. Reads every plan in the house, confirms them, and keeps every show, performance, group and user account straight. |
| **House staff** (`Ruutu10 tiim` role) | People with a theatre e-mail address. May read every submitted plan, but has no further powers over other groups' data. |

Roles are granted per account. Nobody has a role by default except through the
two automatic paths described in §2.4.

---

## 2. Getting in

### 2.1 The four doors

1. **Magic link (the performer's door).** On the technical-plan page you type
   your e-mail address and a one-time login link is mailed to you. If no account
   exists for that address, a lightweight one is created on the spot — there is
   no sign-up form to fill in, no password to choose.
2. **Reminder link.** The reminder e-mails (§6) contain a link that signs you in
   *and* opens the wizard on the right performance in one click.
3. **Ruutu10 SSO (Authentik).** "Continue with Authentik". If your browser
   already holds an Authentik session, the technical-plan page signs you in
   silently the first time you visit it — you simply arrive logged in.
4. **E-mail and password.** Ordinary registration, login, password reset, e-mail
   verification. Two-factor authentication (authenticator app with recovery
   codes) and passkeys are both available under Settings → Security.

### 2.2 What the links are worth

- A **magic login link** is valid for **30 minutes** and may be followed at most
  **4 times**.
- A **reminder link** stays valid until **12 hours after the performance it is
  about**, and may be followed up to **25 times** — writing a plan is rarely one
  sitting. A reminder sent six days out therefore carries a six-day link; one
  sent the night before carries a short-lived one.
- Following a magic link also **verifies your e-mail address** — clicking a link
  in your own mailbox is the same proof a verification mail asks for.

### 2.3 Where a login puts you

You land back where you started. If you asked to log in while reading a shared
plan, the link returns you to that plan. If you started at the wizard, it returns
you to the wizard. Otherwise you land on your dashboard.

### 2.4 Roles granted automatically

- An account whose **verified** address is on one of the theatre's own e-mail
  domains is automatically taken into the house team and given the **staff**
  role. The address must be proven first: SSO proves it outright, other doors
  prove it by e-mail verification. Typing a colleague's domain into a sign-up
  form is not enough.
- The staff role therefore **comes back on its own** — removing it from such an
  account only undoes a mistake, it does not shut somebody out permanently.
- The **technician** role is never granted automatically. It is handed out by
  another technician on the user-management screen.

### 2.5 Restrictions

- You cannot edit your own roles — another technician must do it. This is
  deliberate: the permission that opens the role screen is itself carried by a
  role, so self-editing would let someone lock themselves out.
- Changing an e-mail address (your own, or someone else's from the admin screen)
  marks it **unverified** again.
- Login-link requests are rate-limited to 6 per minute per caller; password
  changes likewise.

---

## 3. The things the system keeps track of

**Group (tiim / team)** — a performing troupe. People belong to groups; groups
own shows.

**Show (lavastus)** — the production as a concept: its name and description.

**Performance (etendus)** — one dated playing of a show, with a start time and
an optional duration. Everything the playings of a show have in common lives on
the show; a performance holds only what can differ.

- A show that one troupe fills has its group on the *show*, and every
  performance inherits it.
- An evening several groups share (an Õppelava, a gala) is **one show played
  once, with a performance per act** — each act naming its own group and its own
  title. The group playing an act is always the act's own if it has one, and the
  show's otherwise.
- Times are always shown on the theatre's clock (Europe/Tallinn). A performance
  with no stated time defaults to 19:00.
- A performance can be marked **draft** (`mustand` / not reviewed). Drafts are
  invisible to the plan wizard and are never chased for a missing plan — see
  §7.3.

**Technical plan (tehnikaplaan)** — what a group needs from the desk for one
performance. Every plan has a permanent share token of the form
`R10-2026-XXXXXXXXXXXX`.

### 3.1 Plan statuses

| Status | Meaning |
| --- | --- |
| **Mustand** (draft) | Saved but not handed in. Still the performer's own. |
| **Esitatud** (submitted) | Handed to the technical crew. |
| **Tehniku kinnitatud** (received) | A technician has picked it up and confirmed it. |
| **Arhiveeritud** (archived) | The performance has been played. |

Submitted and received plans are the ones the crew is working from — they are
what the dashboard counts and what stops the reminder e-mails. Archived plans are
not hidden: they still appear in the crew's overview and are still offered as a
starting point for the next plan of the same show.

---

## 4. Writing a technical plan

This is the system's main flow. It is reached at **`/tehnikaplaan`**, from the
sidebar ("Uus tehnikaplaan"), or straight from a reminder e-mail.

### 4.1 Step 0 — identifying yourself

Writing or saving a plan requires an account. Arriving at the wizard as a guest,
you are asked for your e-mail address and a login link is mailed to you. If you
already hold an Authentik session, this step is skipped silently.

Reading a shared plan does **not** require an account (§4.5).

### 4.2 The seven steps

The stepper on the left shows all seven; steps 3 (Heli), 6 (Lisainfo) and 7
(Ülevaade) are marked optional. Once the performance is chosen you can jump to
any step at any time — nothing else is gated behind completing the previous one,
and no other field is mandatory.

**1. Etendus — choose the performance.**
You are shown every upcoming performance in the house (up to 100, soonest first,
drafts excluded). Tonight's performance stays on the list right up until its
curtain-up. Every plan belongs to a performance, so this is the one choice the
wizard insists on: nothing past this step opens until it is made.

If your evening is not on the list, pick **"Etendust pole nimekirjas"** — the
stand-in performance, offered as a dashed box below the list. The plan reaches
the technical team exactly as any other does; write the show's name, date and
time into the Lisainfo box on the last step, and the crew register the
performance and move the plan onto it afterwards.

Having chosen, you also choose **where to start from**:
- a blank plan, or
- a copy of a plan already handed in for another performance of the same show.

Up to 5 prior plans are offered per show. Those are not only your own: any
submitted, received or archived plan for a performance your groups play counts,
so the next plan for a show can be written by a different member of the group
than the one who sent the last. Copying duplicates the attachments and scene
sound files too, as fresh copies — the plan you copied from is untouched.

Changing the selected performance resets the plan content to a blank slate —
unless the plan has already been saved, in which case it is simply moved onto
the other night, keeping its content, its files and its link. That is how a plan
handed in under the stand-in performance is re-filed once the real one has been
registered.

**2. Standardinfo — what always applies.**
A read-only briefing: where the technician sits, how the countdown clock works,
how the show ends by default, when the house opens and when the technical
run-through is, and what the technician is allowed to do on their own initiative.
It also links to the venue's technical rider. Nothing to fill in — it exists so
performers know what they are *not* obliged to describe.

**3. Heli — sound plan.**
Two yes/no questions, each opening a free-text field when answered yes:
microphones (quantity, placement, whether working or a prop — at most one
wireless handheld is available), and whether the group brings its own musician
(instrument, whether to connect to the PA, power and cabling, placement).

**4. Stseenid — scenes.**
The core of the plan. A scene is a logical or technical section of the show where
the light or sound solution changes. Every plan starts with three pre-filled
scenes (entrance, the scenes themselves, exit) and at least one scene must
remain.

Each scene has: **name**, **light**, **sound**, and **notes**. Light and sound
have one-click preset chips (e.g. "kiire blackout", "üldvalgus", "ruutu10 tunnus
3s") that append to whatever you have already written.

A scene's sound is given **either** as a link **or** as an uploaded file — never
both; switching between the two clears the other. One sound file per scene, and a
new upload replaces the old one. Where the file (or a direct-audio link) is
playable in the browser, it can be played back in place.

Scenes can be reordered by dragging, duplicated, collapsed, and deleted.
A duplicated scene does not carry the original's sound file.

**5. Erivahendid — special equipment.**
An optional list of items (name + how it is used or its limitations), plus two
house questions: whether the technician may use smoke effects (not possible in
the Improkeskus itself; the answer only matters for shows elsewhere), and whether
the technician may make their own scene-affecting offers — with an optional
explanation, which is kept even when the answer is "no".

**6. Lisainfo — anything else.**
Free-text notes and file attachments.

**7. Ülevaade — review and send.**
The finished plan rendered as a document. From here you can:

- **Laadi alla PDF** — print/save the document.
- **Avalik link** — save the plan and get its share link, copied to the
  clipboard.
- **AI ülevaatus** — ask the AI technician for a review (§4.6).
- **Esita tehnikutiimile** — submit it.
- Open the **technician's playback view** — a focused, scene-by-scene reading of
  the plan, meant for the desk.

### 4.3 Saving and drafts

- Nothing is saved to the server as you type. Your progress is kept in **your
  browser** (including which step you were on) and restored the next time you
  open the wizard on that browser.
- A plan is written to the server the first time you create a public link or
  submit it. Until then it exists only locally.
- A plan opened by a share link is never kept as your browser's local draft — the
  half-written plan of your own survives the visit.
- If a reminder link names a performance and your local draft is for a *different*
  performance, the link wins. If the draft is for that same performance, your
  draft is restored — it is work you already started.
- Resubmitting is normal: a submitted plan can be reopened, corrected and
  submitted again, and the crew is notified each time so what they hold is
  always the current version.

### 4.4 Who may change a plan

You may edit a plan if any of the following is true:

- you hold its share link (this is how an author hands editing to someone else),
- you wrote it,
- your group plays the performance the plan is for (or owns the show that
  performance belongs to) — including a colleague's unfinished draft, which is
  exactly what needs fixing,
- you are a technician.

A plan's **author never changes.** Saving a plan you did not write does not
transfer ownership, and such saves are logged.

### 4.5 The public link

Every saved plan has a stable link (`/tehnikaplaan/p/{token}`) that:

- opens **without an account**, straight on the review page, as a read-only
  document,
- includes the attachments and sound files, which stream without a login too,
- invites the reader to log in if they want to edit — and the login link brings
  them back to that very plan.

The link does not expire. Anyone holding it can read the plan, and once signed
in, edit it.

### 4.6 The AI review

"AI ülevaatus" sends the plan as it currently stands to an AI reviewer playing
the role of an experienced house technician, and returns written suggestions.

- It works on unsaved content — you can review before ever saving.
- It is advisory only. The screen says so: the suggestions are not obligations.
- It is rate-limited to 15 requests per 10 minutes, and returns a plain error if
  the integration is not configured or the call fails.

### 4.7 Submitting

Pressing **Esita tehnikutiimile** saves the plan, sets its status to
**Esitatud**, and stamps the submission time. Immediately afterwards:

- the plan's author receives the full plan by e-mail as a record of what they
  sent,
- the technical crew's address receives the same document,
- the plan appears in the crew's overview and stops the reminder e-mails for that
  performance.

The e-mailed document, the printout and the on-screen review are the same
document rendered by the same rules.

### 4.8 Limits when filling in

- **Attachments:** max 20 MB per file. Allowed types: doc, docx, pdf, jpg, jpeg,
  png, gif, mp4, mov, avi, mkv, mp3, wav, ogg, qlc, txt, webp.
- **Scene sound files:** mp3, wav, ogg only.
- Files are checked by content, not just by name — a renamed script is rejected.
- Field lengths: show description 5 000 characters; scene light/sound/notes and
  the sound detail fields 2 000 each; equipment usage 1 000; free-text notes
  10 000; duration 1–240 minutes.
- Uploads and discards are limited to 20 per minute; other wizard calls to 200
  per minute.
- Files uploaded but never attached to a saved plan are deleted automatically
  after 72 hours.

---

## 5. After the plan is handed in — the technical crew

### 5.1 The plan overview (`Saadetud plaanid`)

Available to anyone who may read all plans (technicians and house staff). It
lists **every plan in the house, drafts included**, ordered by performance date
with the latest first — what is coming up, or has just been played, is what the
crew looks for, not the archive. The plans filed under the stand-in performance
("Etendust pole nimekirjas") are dated years ahead and so gather at the top,
which is where they want to be — those are the ones still needing a real night.

Each row opens a detail page showing the plan, who wrote it, which performance it
is for, and when it was submitted.

### 5.2 Changing a plan's status

On the detail page a technician can move a plan to any status. Reading all plans
and changing their status are **separate rights** — house staff can read but not
change.

The one transition that has a consequence is **Esitatud → Tehniku kinnitatud**:
the plan's author is e-mailed that the crew has picked it up. A plan whose author
has since been removed simply has nobody to tell. Every status change is logged
with who made it.

---

## 6. Reminders

Performers are chased automatically for plans that have not been handed in.

**When.** Two reminders per performance: **6 days** before, and **30 hours**
before. Both carry the same text — the second is not a sterner letter, it is the
same letter arriving when there is no longer time to forget about it.

**Who gets them.** Every member of the group playing the performance — the act's
own group on a shared evening, the show's group otherwise. Each performer gets a
link of their own, since the link signs its holder in.

**The crew** gets its own separate copy, listing who was chased. That copy
deliberately carries no login link.

**What stops them.** A submitted or received plan for that performance. A draft
does not count — it was never handed in.

**What is never chased:**
- performances marked as drafts,
- performances with no group (nobody to write to),
- performances whose group has no members — this is logged as a warning and
  reconsidered later, in case somebody joins in time,
- reminders that were already overtaken: a performance registered three days
  before it happens never had a six-day window, and a system catching up after
  downtime sends only the latest due reminder rather than both at once.

Each reminder is sent **exactly once** per performance, whatever happens to the
scheduler. The whole reminder mechanism can be switched off house-wide.

---

## 7. Managing shows and performances

### 7.1 Shows (`Lavastused`)

Everyone signed in can open this screen; what it lists is what you may reach:

- shows owned by a group you belong to, **and**
- shows your group merely plays a performance on (a guest slot on somebody
  else's evening).

Technicians see every show in the house, including shows with no owning group —
which are reachable no other way.

You can create a show (choosing an owner from the groups you belong to; a
technician may choose any group), rename it, change its description, and hand it
to another group. A show is never moved somewhere its editor cannot follow it.

**The two rights are deliberately different.** A guest troupe can reach the
evening it plays on in order to correct *its own* performance, but the show
itself — its name, its owner, its deletion — stays with the show's group.

**Deleting a show** puts it aside (soft delete) and takes its performances with
it, so nothing is left pointing at a show the rest of the app no longer shows.
The plans written for those performances keep their trail.

### 7.2 Performances

Managed from a show's edit page. A performance has a date and start time,
optionally a duration, optionally its own title and its own performing group (for
shared evenings), and a draft flag.

- **Adding** a performance is the show group's right alone — a guest troupe may
  correct its own slot but not put more of its own on the bill.
- **Editing and deleting** a performance is open to the show's group, the group
  playing that performance, and technicians.
- **Deleting a performance does not delete the plans written for it.** They go on
  pointing at it — the performance is only hidden, so restoring it joins the two
  back up — but until then they read as plans with no show, group or date. The
  screen warns you when there are any; the way back is to restore the
  performance, or to open the plan and move it onto another one from its first
  step.

### 7.3 Draft performances

A performance marked as a draft is one the automatic import registered and nobody
has reviewed yet — its date may be wrong, or the night may not be happening at
all. Until an admin clears the flag it is:

- kept out of the wizard's performance picker,
- never chased for a missing plan.

It still appears in the management screens, marked as unreviewed.

### 7.4 The house-wide performance overview (`Etendused`)

Technicians get a single list of every performance in the house, newest first,
with how many plans each has. Everyone else reaches their own groups' dates
through Lavastused instead.

---

## 8. Groups and membership

### 8.1 Your own groups (Settings → Teams)

- Create a group. Whoever creates it owns it.
- Switch which group you are currently working in — the dashboard and the
  sidebar follow it.
- Invite people by e-mail. **Invitations expire after 3 days** and can be
  cancelled while pending. Expired invitations are cleaned up nightly.
- Accept or decline invitations from your dashboard. Accepting switches you into
  that group. Accepting an invitation to a group you are already in changes
  nothing — you keep the role you had.
- Leave a group (owners cannot leave their own group). When you leave, are
  removed, or the group is deleted, you are moved to whichever of your remaining
  groups comes first alphabetically. If you have none left, you are left without
  a current group and the screens cope with that.
- A group's name becomes its URL slug, so **renaming a group changes every link
  that names it**. Certain names are reserved and refused.

### 8.2 Roles inside a group

| Role | Can |
| --- | --- |
| **Owner** | Everything: rename, delete, add/remove members, change member roles, invite, cancel invitations. |
| **Admin** | Rename the group, invite, cancel invitations. |
| **Member** | Nothing administrative. |

Owner cannot be assigned — it belongs to whoever created the group.

### 8.3 The house-wide group overview (`Tiimid`)

Technicians manage every group in the house from here: create, rename, delete,
and add, re-role or remove members directly. A plain member opening this screen
sees their own groups and is told up front what they may not change, rather than
finding out by being refused.

---

## 9. User accounts (`Kasutajad`)

Open only to technicians. Unlike shows and groups, nothing here is scoped to what
you belong to — you see every account in the house or you are refused entry.

The list shows each account's name, address, the roles it holds and how many
groups it stands in. On an account you can:

- correct its name and e-mail address (which marks the address unverified
  again),
- grant and revoke roles.

Granting a role you already granted, or revoking one that is not held, changes
nothing — a double-clicked toggle is harmless. **Nobody may edit their own
roles.** Every role change is logged with who made it.

---

## 10. The dashboard

The landing page after signing in. It sits under your current group — switching
groups moves you between dashboards — but what it counts is house-wide.

Everyone sees:
- pending invitations to groups, with a one-click accept,
- how many performances are still ahead house-wide, when the next one is, and
  how many upcoming performances still have no plan.

Technicians and house staff additionally see a timeline of the **8 most recently
submitted plans**, linking straight into them.

---

## 11. What the system does on its own

| Job | When | What it does |
| --- | --- | --- |
| **Planka import** | Daily | Reads the production board's cards and registers the shows and performances they announce. New performances arrive as **drafts** awaiting review. Shows an admin has deleted here are never resurrected. Cards can be excluded by label. |
| **Reminders** | Hourly | Sends any technical-plan reminder that has just fallen due (§6). Quiet most hours. |
| **Archiving** | Daily | Moves submitted and received plans to **Arhiveeritud** once their performance was played more than 24 hours ago. A performer's own draft is never archived — it was never handed in. |
| **Invitation cleanup** | Daily | Deletes expired group invitations. |
| **Upload cleanup** | Weekly | Deletes staged files older than 72 hours that were never attached to a plan. |

### 11.1 How the import reads a card

A card announces one or more nights, and a night one or more acts. An evening one
troupe fills becomes a show played once; an Õppelava becomes one show played once
with a performance per group. Matching is by name (show), by show + date (night)
and by name within the night (act), so re-importing the same card adds nothing.

Because the reading is done by AI, the **reasoning behind each imported record is
kept** and can be opened from the show and performance screens by technicians and
house staff — this is how a wrong date gets traced back to the card it came from.
Records show whether they were entered by hand or by the import.

---

## 12. Who can see what — summary

| | Own plans | Own group's plans | All plans | Change plan status | Own group's shows | All shows / performances / groups | User accounts & roles |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Performer (no role) | ✅ | ✅ | — | — | ✅ | — | — |
| House staff | ✅ | ✅ | ✅ read | — | ✅ | — | — |
| Technician | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

Anyone holding a plan's **share link** can read that plan without any account,
and edit it once signed in.

Two further notes on the boundaries:

- Reading all plans and changing their status are separate rights: house staff
  can read but not confirm.
- A colleague's *unfinished draft* is editable by their group (that is what a
  draft is for) but is never offered as a starting point for a new plan — only
  submitted, received and archived plans are.

---

## 13. Things that commonly surprise people

- **Nothing in the wizard is mandatory.** A plan with every field blank can be
  submitted. The standard-info step exists so performers know what happens when
  they say nothing.
- **A plan is not saved until you create a public link or submit it.** Before
  that it lives only in your browser.
- **The share link is the edit permission.** Sending it to someone signed in
  gives them the ability to change the plan.
- **Submitting again is expected.** The crew is re-notified each time.
- **A reminder link from an old e-mail may open a blank wizard.** That happens
  when the performance it named has since been played or put back to draft; the
  wizard simply opens at the beginning with the list to choose from.
- **Renaming a group breaks links that contain its name.**
- **Deleting a performance leaves its plans behind**; deleting a show takes its
  performances with it.
- **Removing the staff role from a theatre address does not stick** — it is
  re-granted the next time that address is proven.
