# AGENTS.md - for every AI tool in this repo (Codex, Cursor, Copilot, Kiro, Antigravity ...)

This repo is **ระบบผู้บริหารโครงการ** (`https://www.mesuk.co.th/agent`). Read `CLAUDE.md` first: its rules apply to every AI, not only Claude.

The part that is easy to miss is section **0.1 Work log, pending tasks, browser test, upload**. In short:

- Every finished task that changed files is recorded in the user's daily report, which lives in the sibling project happyest: write a draft JSON (UTF-8, Thai text in the file, never on the command line) and run `node ../happyest/scripts/worklog.cjs <draft.json>`. Use `"area": "MEMBER"`, brief lines like `"[MEMBER] บันทึกมิเตอร์ เพิ่มปุ่มส่งออก Excel"`, `files` as paths in this repo, `pages` as `{"label", "url": "/meters", "site": "agent"}`, and `"agent"` = your tool's name.
- Text the user pastes from LINE is a request to record and do: `node ../happyest/scripts/worklog.cjs todo list`, `todo add <draft.json>` (one item per request, `area` MEMBER), do the work, put `"todo": [N]` in the work-log draft, finish with `todo reply <numbers>` for LINE.
- Test in a real browser before reporting: `MSYS_NO_PATHCONV=1 node ../happyest/tests/browser/check.cjs --app=agent --as=manager --clean /dashboard /meters`.
- These commands write only happyest's work-log store; never edit happyest's code from here.
