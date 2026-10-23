# EcoSight User Manual

EcoSight is a progressive web app that identifies land animals from a camera or photo using CLIP (Transformers.js) as the primary browser-run recogniser, with a MobileNet fallback - then shows educational info and suggested conservation actions for each species. All inference runs in your browser.

For species that are not in the small Animal library, EcoSight can suggest the closest match from a list of 3,750 species (or 20,000 on request) and add it to the library as a user-contributed record.

## Getting started

### Installation (development)

Requirements: PHP 8.4+, Composer, Node.js + npm.

```powershell
composer install
npm install
npm run download:clip
npm run build
php artisan migrate --seed
php artisan serve
```

`npm run download:clip` downloads the CLIP vision encoder, text encoder, and the pinned Transformers.js bundle into `public/` (these assets are not committed). This seeds 33 supported animals, 16 breeds/varieties, conservation actions, contact data, plus demo users `demo@ecosight.test` / `password` and `admin@ecosight.test` / `password`.

### Live DB note

If MySQL is not running locally, use SQLite for development:

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE='C:\absolute\path\database\database.sqlite'
php artisan migrate:fresh --seed
```

> When smoke-testing over HTTP, run a direct server so the SQLite override reaches web requests: `php -S 127.0.0.1:8000 -t public` (Laravel's `php artisan serve` strips env overrides unless `--no-reload`).

## Signing up

1. Open the app and click **Sign up**.
2. Enter your name, email, and password (make sure your email and password are exactly correct — there is no verification step in the demo).
3. You are redirected to the dashboard.

## Scanning an animal

1. Open the **Scanner** (button in the top navigation).
2. Click **Start camera** and grant camera permission. EcoSight needs a secure context (HTTPS or `localhost`).
   - No camera? Use **Upload a photo**.
   - Multiple cameras? **Switch camera** appears automatically.
3. Wait for CLIP to finish loading (the spinner shows which model file is loading; after the first visit your browser caches the files).
4. Point the camera at a supported animal - any of the 33 animals in the Animal library. Hold the animal in view for a moment: the camera needs three consistent frames before it shows a result.
5. If several animals look equally likely, EcoSight asks **Which animal is it?** - tap the correct one. If the result is low-confidence, the card appears with a **Confirm & save** button; only press it if the animal is right.
6. Some animals (dogs, cats, horses, cattle) show an optional **Breed or variety** selector. CLIP preselects its best guess; change it if you know the breed, or leave it on "Not sure".
7. The educational card appears with habitat, climate impact, conservation status, and suggested conservation actions.
8. Click **Save scan** (or **Confirm & save**) to store it in My Scans.

### When a species is not in the library

The Animal library covers 33 species, so most real-world animals (most birds, insects, reptiles, and smaller mammals) are not in it. When the scanner cannot match anything in the library, it says so and opens the **Suggested species** panel:

1. EcoSight scores your photo against a species list of 3,750 species and shows the closest matches with their match probability.
2. Choose **Search 20,000 more species** to download the extended list (about 18 MB the first time). The download is shown with a progress bar and is then kept in your browser, so later scans use it without downloading again. If the file fails to verify, EcoSight discards it and asks you to try again.
3. Pick a species to see its Wikipedia summary, the conservation status, and the source link, then:
   - **Add to the library** - creates a new animal page for that species. Your addition is marked *Waiting for review* until a reviewer confirms it, and it is credited to you on the animal page.
   - **Add as a type of …** - offered when the species belongs to the same genus as an animal that is already in the library (a specific fox, for example). It is then listed as a variety of that animal.

The suggestion is the closest match for your photo, not a guaranteed identification. Always compare the summary with what you can see before adding a species.

## Reviewing a suggested species

If you have reviewer access, open the animal page of a user-added species and use the **Mark as reviewed** button (or **Undo review**) at the top of the page. The banner changes from *Waiting for review* to *Reviewed*, and the page shows who added the species and where the information came from.

### Pausing and resuming

Use **Pause** during a scan to stop the detection loop while keeping the camera stream, then **Resume** to continue.

## Giving feedback on a scan

After you **Save a scan**, the scanner asks "Was this result correct?":

- **Yes** — records a correct recognition result.
- **No** — pick the actual animal from the dropdown, then submit.

Feedback powers the Performance report's recognition accuracy and is stored per scan (one entry per scan).

## Understanding My Scans

- The **My Scans** page lists every scan you saved, newest first.
- Use the **From / To** date filters, then **Filter** to narrow results.
- Every scan links to its animal page when the record exists.
- **Export CSV** downloads only *your* scans, including the model used, runner-up, breed/variety, and whether you confirmed the result.
- Confirmed low-confidence results are marked **user confirmed**, so you can tell them apart from automatic matches.

## Completing conservation actions

Every animal page lists its conservation actions (the original ten COCO-SSD animals have 2; the MobileNet wild animals and pets have 3). Use **Mark as done** to record that you took the action, and **Undo** to revert. You can also mark the "Today's conservation action" straight from the dashboard, or from the scanner's result card.

- Complete **1 action** → "First step" badge.
- Complete **5 actions** → "Five actions" badge.
- Take action for **every supported animal** → "Animal champion" badge.

## Sharing feedback via the survey

Use **Share feedback** on the dashboard to rate four aspects of the experience (ease of use, speed, clarity, usefulness) from 1 to 5, and optionally leave a comment.

## The Performance report

The **Performance** page aggregates data across all users:

- Average / median / P95 response time of the on-device model.
- Average model-load and camera-start time (from client events).
- Recognition accuracy (from scan feedback).
- Low-confidence rate (client low-confidence events vs saved scans).
- Detection-failure counts by reason (e.g. no-animal-detected, insecure-context).
- Average survey ratings and per-animal confidence stats.

Download the report as CSV (**Download report CSV**) or your own scans as CSV (**Download my scans CSV**).

## Animal library

Browse all supported animals, search by name/scientific name/COCO label, and filter by conservation status. Signed-in staff can add, edit, and delete animals and their conservation actions from the library pages.

Animals that you or another user added from the scanner appear in the library too. They are marked *Waiting for review* until a reviewer confirms them, and each page links to the Wikipedia article it was created from.

Every animal page also lists the general conservation actions for its group (for example, habitat protection and anti-poaching steps for mammals). These apply to every species in the group, so they are shown alongside the species-specific actions.

## Troubleshooting

| Symptom | Fix |
|---|---|
| "Camera needs HTTPS or localhost" | Use `http://localhost` (not `127.0.0.1`) or an HTTPS URL. |
| Camera permission denied | Allow camera access in your browser's site settings, then click **Start camera** again. |
| Model never loads | CLIP files are served from this site (run `npm run download:clip` if they are missing); the TensorFlow.js and MobileNet fallback files come from a CDN. Check your connection. If only the fallback fails, a notice appears and CLIP scanning keeps working. |
| "Low-confidence result" repeatedly | Improve lighting, move closer, and fill more of the frame with the animal. Confirm only when you are sure. |
| CLIP keeps saying "Hold steady" | Keep the animal centred and still for a couple of seconds; the camera requires three stable frames. |
| Unsure which animal was seen | Use the closest-matches list shown in the low-confidence state. |
| Scanner says recognition is unsupported | EcoSight only covers the 33 land animals listed in the Animal library. Photos without an animal are rejected as "No animal in view", and confident fallback classes outside the library are reported as "Not in the library yet" and cannot be saved. The open-vocabulary panel then suggests the closest species from the 3,750-species list. |
| "Search 20,000 more species" fails | The extended list is downloaded from this site. Check your connection and try again; a failed or corrupted download is discarded automatically, so a retry is always safe. The 20,000-species list is only needed for the extended search - the basic suggestions work without it. |
| A suggested species is wrong | Do not add it. Take a clearer photo, or pick a different suggestion. The suggestions are the closest visual matches, so similar-looking species can be confused. |
| A user-added animal shows "Waiting for review" | A reviewer has to confirm user additions before they count as verified library records. You can still open the page and use the actions. |