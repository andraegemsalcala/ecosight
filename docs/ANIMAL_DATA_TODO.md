# Animal data — verification notes and open tasks

EcoSight's animal library is built in two layers:

- `AnimalSeeder` — the ten COCO-SSD classes (`bird`, `cat`, `dog`, `horse`,
  `sheep`, `cow`, `elephant`, `bear`, `zebra`, `giraffe`).
- `AnimalExpansionSeeder` — the MobileNet-detected set (20 species), so the
  library totals 30 entries.
- `AnimalPetSeeder` — the three household pets detected by MobileNet (guinea
  pig, hamster, domestic rabbit), bringing the scannable library to 33.

Every MobileNet `model_labels` value is copied **exactly** from the
ImageNet-1000 class list shipped with `@tensorflow-models/mobilenet`
(`src/imagenet_classes.ts`, `exports.IMAGENET_CLASSES`, 1000 entries). The
strings were verified programmatically against that list; nothing is guessed.

## ImageNet-1000 classes the project originally wanted but does not contain

These animals were requested for the MobileNet set but are **not present** in
ImageNet-1000, so they could not be seeded as scannable records:

| Requested animal | Why absent | Replaced by (verified class) |
|---|---|---|
| Raccoon | No `raccoon` entry exists | `otter` → class 360 |
| Moose | No `moose` entry exists | `leopard` → class 288 |
| Red deer / wapiti | No `red deer` / `wapiti` entry | `gazelle` → class 353 |
| Adélie / rockhopper penguin | Only `king penguin` (145) exists | kept `king penguin` |

If the MobileNet model is ever swapped for a network with a different class
list, re-run `scripts/_`-style verification before adding these species.

## Species mapping notes

- The ImageNet entry `jaguar, panther, Panthera onca, Felis onca` (290) maps to
  **Jaguar**; do not confuse it with the leopard (288) entry.
- The ImageNet entry `lynx, catamount` (287) is ambiguous in the source (covers
  lynx/bobcat); the record covers both (scientific name `Lynx lynx / Lynx rufus`).
- The only kangaroo-family entry is `wallaby, brush kangaroo` (104); the record
  is labelled **Kangaroo**, scientific `Macropodidae`.
- `red fox, Vulpes vulpes` (277) and `kit fox, Vulpes macrotis` (278) both map
  to the single **Red fox** record.
- The generic `pelican` (144) entry maps to a species-agnostic **Pelican**
  record; IUCN status varies by species.
- Household pets are mapped to the **only** matching ImageNet strings:
  `guinea pig, Cavia cobaya` (338), `hamster` (333), and `Angora, Angora rabbit`
  (332). ImageNet has **no plain "rabbit"** class; `wood rabbit, cottontail`
  (330) and `hare` (331) describe wild species and are not mapped to a pet
  record. `Cavia cobaya` in ImageNet is an older synonym of the pet guinea
  pig's scientific name *Cavia porcellus*.
- Pet conservation statuses say `Domesticated — ...` because IUCN assesses wild
  species. The wild European rabbit (*Oryctolagus cuniculus*) is listed
  Endangered; the surviving wild Syrian hamster is Vulnerable — both noted in
  the seed data rather than asserted as blanket pet statuses.

## IUCN statuses to re-check before relying on them

These are unlikely to change anything, but re-verify at the next data pass
(constructed from IUCN Red List pages; none invented):

- `gorilla` → `Critically Endangered` (western gorilla, *Gorilla gorilla*).
- `tiger` → `Endangered`; `red panda` → `Endangered`.
- `cheetah` / `lion` / `leopard` / `polar bear` / `koala` / `giant panda` → `Vulnerable`.
- `jaguar` → `Near Threatened`; `otter` → `Near Threatened` (Eurasian otter).
- `pelican` and `gazelle` are intentionally set to the literal string
  `Check IUCN Red List (...)` because the ImageNet class is species-agnostic.

## The open-vocabulary layer

The seeded library above is the scannable set. Species outside it are covered
by the browser-side vocabulary built by `scripts/build-animal-vocabulary.mjs`:

- Tier 1 - 3,750 species, always available in the scanner (mammals 800,
  birds 1,300, reptiles 600, amphibians 350, insects 500, arachnids 200).
- Tier 2 - 20,000 species, downloaded on request (roughly even per group).

Vocabulary rows are Wikidata species with an English name and an English
Wikipedia article. The taxonomy data is CC0 and the article text is CC BY-SA
4.0; the app shows the article link and the licence, never the article text
without attribution. Marine mammals, sea turtles, fish, and fossil-only taxa
are excluded because the app is about land animals.

Tier 1 and tier 2 never contain the same species: the tier 2 selection
excludes every tier 1 identifier. The committed assets are verified with
`node scripts/validate-vocab.cjs` (checksums, row counts, and the tier
overlap).

## Open tasks

- [ ] Add a real photo-spec link per species (external images intentionally
      avoided in the app).
- [ ] If a future model release lists `raccoon`, `moose`, or `red deer`, add
      the corresponding records + three conservation actions each.
- [ ] Consider splitting the generic `pelican`/`gazelle` records into species
      pages (e.g. Dalmatian pelican, Dama gazelle) with species-specific IUCN
      statuses.