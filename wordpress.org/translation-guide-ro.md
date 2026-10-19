# Romanian (ro) translations: how to fix them for the translate.wordpress.org editors

General notes for any plugin. They come from the review of Rotation Tournaments by the Romanian editor
Dan Caragea (2026-10-18), the team style guide <https://ro.wordpress.org/team/handbook/ghid/> and the glossary
<https://translate.wordpress.org/locale/ro/default/glossary/-export/> (CSV).

## 1. Get the feedback
- Status of the plugin: `https://translate.wordpress.org/locale/ro/default/wp-plugins/<slug>/`
  (look at "Changes requested" for `dev`, `dev-readme`, `stable`, `stable-readme`).
- Export only the returned strings:
  `https://translate.wordpress.org/projects/wp-plugins/<slug>/dev/ro/default/export-translations/?format=po&filters[status]=changesrequested`
  (the same for `dev-readme`).
- The reasons are in each string's discussion, `https://translate.wordpress.org/projects/wp-plugins/<slug>/dev/<original_id>/ro/default/`.
  The original IDs are in the list page (`?filters[status]=changesrequested`, 15 rows per page, `&page=2` ...)
  as `id="editor-<original_id>-<translation_id>"`. The JSON endpoint
  `.../dev/ro/default/-get-translation-helpers/<original_id>/?translation_id=<translation_id>` returns the discussion HTML.
- translate.wordpress.org answers HTTP 429 after ~10 quick requests from one IP and stays blocked for many minutes.
  Wait 10+ s between requests, or read the discussion pages with WebFetch (another IP).
- The editor usually writes only a reason (Style Guide, Grammar, Glossary, Bad Quality (AI?)) and no text.
  "is being discussed here" links to one comment that covers a group of strings (e.g. the whole old changelog).
  So fix the reason in **the whole PO file**, not only in the returned strings - the next import would repeat it.

## 2. Rules (what the editor checks)
- **Informal address, 2nd person singular.** Never "dumneavoastră", "vă", "puteți", "-ți" imperatives.
  - Buttons and actions: imperative singular - Salvează, Șterge, Adaugă, Introdu, Alege, Selectează, Caută, Copiază, Continuă.
  - "Click" -> "Dă clic"; "Please" -> "Te rog".
  - Yes/no setting questions: indicative 2nd person - "Afișezi ...?", "Permiți ...?", "Ștergi ... la dezinstalarea modulului?".
- **No grammatical gender** for the reader: "Sigur?" (not "Ești sigur?"), "Te-ai înscris." (not "Ești înscris."),
  "Nu te-ai autentificat." (not "Nu ești autentificat."), "Răspunzi de ..." (not "Ești responsabil ..."),
  "Acum faci parte din turneul %s" (not "Ai fost adăugat ..."), "singura persoană" (not "singurul").
- **The software speaks in the 1st person singular:** "Am copiat legătura.", "Am actualizat articolul.", "Încerc ...".
- **Changelog** in the 1st person: "Am adăugat ...", "Am corectat ...", "– am corectat ..." after a prefix.
  The passive "A fost adăugată ..." and noun phrases like "Posibilitatea de a ..." were rejected as "Bad Quality (AI?)".
- **Capitals:** only the first word, names of commands/pages/buttons and proper nouns. Lower case after ":" and "-".
  Setting names inside a sentence go into quotes: Parametrul „Numărul minim de ...”.
- **Quotes:** „text” (U+201E, U+201D). Straight quotes only inside HTML attributes.
- **Diacritics with comma:** ș ț (U+0219, U+021B), never ş ţ with cedilla.
- **Punctuation:** no space before . , ; : ! ? ; "..." -> "…" is fine; "&" -> "și" unless it is a name.
- **Numbers and dates:** decimal comma (3,14), thousands with a dot (1.000), "20 de ..." for 20+;
  date dd.mm.yyyy (PHP `d.m.Y`), time 22:17 (`G:i`).
- **Do not translate** plugin, theme and service names (keep e.g. "Rotation Tournaments"), URLs, placeholders
  (%s, %1$s, %d - keep all of them), HTML tags, shortcode/parameter names.
- **Avoid literal (machine-like) translation.** Short clear sentences; rephrase awkward English.

## 3. Glossary terms that came up
| English | Romanian |
|---|---|
| plugin | modul (plural module) |
| module | extensie |
| remove | înlătura (înlătură, înlăturare) - not "elimina" |
| link | legătură (feminine: o legătură, această legătură) - not "link" |
| browser | navigator |
| icon | icon (plural iconuri) - not "pictogramă" |
| shortcode | scurtcod (plural scurtcoduri) - not "cod scurt" |
| email | email (no hyphen; plural emailuri) |
| share | partaja |
| view | vedea; noun vizualizare |
| play (a video) | rula |
| update / upgrade | actualiza / actualizare |
| edit | edita, editare |
| display | afișa, afișare |
| homepage | prima pagină |
| dashboard | Panou control |
| tab | filă |
| endpoint | punct-final |
| nonce | nunic |
| player (media) | player - for a sports player use "jucător" |
Check the full CSV for every new string: the editor's tool underlines glossary words and flags a different translation.

## 4. Deliver
- Fix the local PO files (`languages/ro/...-ro.po`) completely.
- Build small import files with only the returned strings: take the `changesrequested` export, fill each msgstr
  from the fixed local PO (by msgid) and give these files to the owner to import on translate.wordpress.org.
- Check placeholders and that every returned string really changed before handing the files over.
- Add new findings (new glossary terms, editor's reasons) to the plugin's CLAUDE.md.
