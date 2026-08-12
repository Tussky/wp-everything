# CODING_STANDARDS.md — Engineering

Binding on every code change. Where this conflicts with a task, this file wins —
say so and stop.

## 1. The diff

- **Smallest change that satisfies the definition of success.** Nothing else in
  the same commit.
- Touch only what the task named. Something outside it → escalate, don't decide.
- No drive-by renames, reformatting, dependency bumps, or "while I was in here"
  fixes. They hide the real change and make reverts unsafe.
- Dead code is deleted, not commented out. Git remembers.

## 2. WordPress plugin rules (wp→search)

- **Escape on output, sanitize on input** — `esc_html`, `esc_attr`, `esc_url`,
  `wp_kses_post` on the way out; `sanitize_text_field`, `absint` on the way in.
- **Every AJAX/REST endpoint checks a capability and a nonce.** No exceptions,
  including admin-only screens.
- **Every database query is prepared.** `$wpdb->prepare()`, always.
- Prefix every global function, class, hook, and option.
- Never edit a plugin whose header `Author:` is not this company.
- WordPress Coding Standards for PHP; the repo's `phpcs` config is the arbiter.

## 3. Naming and structure

- Names say what a thing is, not how it works. `Posts_Indexer`, not `Handler2`.
- One class per file, file named for the class.
- Match the surrounding code's idiom, formatting, and comment density.
  Consistency beats personal preference.
- Comments explain **why**, never **what**. If the *what* needs a comment,
  rename something instead.

## 4. Errors

- Fail loudly and specifically. Never catch and continue silently.
- An error names the thing that failed and the value that caused it.
- Never swallow an error to make a check pass. A green result over a broken
  cause is a lie with a receipt.

## 5. Verification

- Every change ships with the thing that proves it and its real, pasted result.
- Test the failure path, not only the happy path.
- Never claim "verified" for something you did not personally run.

## 6. What not to write

- Documentation, guides, translation templates, banners, icons, or release
  assets — until a human has seen the feature work. This gate is absolute.
- Abstractions with one caller. Options nobody asked for.
- Defensive code for conditions that cannot occur.
- New `.md` files outside the plugin directory.

## 7. Committing

- One logical change per commit. Message: `IA-<n>: <what changed, imperative>`.
- Never commit to `main`. Never push unless asked.
- Never commit secrets, `.env` values, or sandbox credentials.

## Why this is short

Every rule here traces to something that cost this company real output. If one
stops earning its place, say so and we delete it.
