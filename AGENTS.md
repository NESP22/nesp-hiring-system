# Agent instructions — NESP22 repositories

These rules apply to every AI agent (Claude, Codex, Copilot, etc.) and to humans working in any NESP22 repo. They exist so that any agent can pick up work on any machine without creating a mess.

## Where the code lives

| Machine | Canonical folder | Notes |
|---|---|---|
| Mac | `~/Projects/<repo-name>` | One clone per repo, nothing else. |
| Windows (THEBOSS) | `C:\Projects\<repo-name>` | Same layout, same repo names. |
| GitHub | `https://github.com/NESP22/<repo-name>` | Source of truth. |

Work ONLY in the canonical folder. Never clone a second copy of a repo somewhere else (Desktop, Documents, Codex/, ChatGPT/, /tmp) to "review" or "experiment" — make a branch instead. Old scattered copies were moved to `_archive-repos/` on 2026-09-17 and should not be used.

## Branch rules

1. `main` is protected in spirit: **never commit or push directly to `main`.** For `team-hub-app`, `main` auto-deploys to production.
2. Start every task from fresh `main`: `git checkout main && git pull --ff-only`.
3. Work on a branch named `<type>/<short-description>-<YYYYMMDD>`, e.g. `fix/payroll-state-20260917`, `feat/parent-rsvp-20260917`, `chore/deps-20260917`.
4. Push the branch and open a pull request. Merge only after checks pass. Delete the branch after merge.
5. Before ending a session or switching machines: **commit and push.** Uncommitted work on one machine is invisible to the other and to every other agent. If it is not ready, push it anyway on a `wip/<machine>-<date>-<topic>` branch.
6. Never rewrite history on a shared branch (`push --force`, rebase of pushed commits) without explicit permission from Craig.
7. Do not `git stash` and walk away. Stashes are local-only and get lost. Commit to a `wip/` branch instead.

## Recovered work-in-progress (2026-09-17 cleanup)

Branches named `wip/mac-20260917-*` and `wip/win-20260917-*` contain uncommitted work and stashes that were auto-rescued from scattered clones. They are **unreviewed backups**, not tested code. Do not merge them blindly. When a task touches an area one of them covers, inspect it with `git diff main...wip/<branch>`, cherry-pick what is useful, and then ask Craig before deleting the branch.

## Large files

Do not commit files over 20 MB, model weights (`.pth`, `.ckpt`, `.pt`, `.safetensors`), videos, or archives. Keep them out of the repo (ignored folder, cloud storage, or Git LFS if Craig sets it up). A `.wip-skipped-files.txt` in a `wip/` branch lists files that were intentionally left out of a rescue commit.

## Secrets

Never commit `.env`, API keys, tokens, or Supabase service keys. If one is found in history, tell Craig immediately; do not try to "fix" it silently.

## Machine sync checklist (start of any session)

```
cd ~/Projects/<repo>        # Mac        or        cd C:\Projects\<repo>   # Windows
git fetch --all --prune
git status                  # must be clean; if not, commit to a wip/ branch first
git checkout main && git pull --ff-only
```

If `git status` is not clean and you did not make the changes, stop and ask Craig before discarding anything.
