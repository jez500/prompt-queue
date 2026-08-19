/**
 * Placeholder copy for the prompt body, rotated so a new draft rarely opens on
 * the same line twice.
 *
 * The list is deliberately opinionated rather than instructional — the field is
 * empty for a second or two at most, and a flat "Write your prompt" reads as
 * dead space. Keep them short enough to sit on one line in the narrow pane.
 */
const BODY_PLACEHOLDERS = [
    'Say the quiet part. The agent cannot read minds…',
    'Be specific. Robots are famously bad at guessing…',
    'Every good prompt starts life as a bad one…',
    'Describe the whole task, not the half you remember…',
    'Brief it like a very fast intern with no context…',
    'Vague in, vague out. Your call…',
    'Paste the mess now, tidy it later…',
    'Explain it here once, never explain it again…',
    'The agent does exactly what you wrote. Choose wisely…',
    'Half-formed thoughts welcome. Editing is free…',
    'Write it down before you forget why it mattered…',
    'What would you ask if nobody ever got tired of questions…',
];

/*
  Module state, so the rotation carries across every draft opened in one visit
  rather than restarting per component. The random start stops a reload from
  always serving the same opening line.
*/
let cursor = Math.floor(Math.random() * BODY_PLACEHOLDERS.length);

/**
 * The line currently on show, without moving the rotation on.
 *
 * The detail pane unmounts every time the narrow layout returns to the list,
 * so it needs a starting value that does not consume one. Advancing here as
 * well as in the watcher stepped the cursor twice per draft, which on an
 * even-length list means half of it is never seen.
 */
export function currentBodyPlaceholder(): string {
    return BODY_PLACEHOLDERS[cursor];
}

/**
 * The next placeholder in the rotation. Call once per draft opened.
 */
export function nextBodyPlaceholder(): string {
    cursor = (cursor + 1) % BODY_PLACEHOLDERS.length;

    return BODY_PLACEHOLDERS[cursor];
}

/**
 * The whole list, for the test that pins the rotation.
 */
export function bodyPlaceholders(): readonly string[] {
    return BODY_PLACEHOLDERS;
}
