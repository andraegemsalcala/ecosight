<?php

return [
    'scope' => [
        'sdg' => 15,
        'summary' => 'Land animals only: mammals, birds, reptiles, amphibians, insects, and arachnids. Fish, marine mammals, sea turtles, and other invertebrates are outside the default scope.',

        'groups' => [
            'mammals' => [
                'label' => 'Mammals',
                'noun' => 'Mammal',
                'qid' => 'Q7377',
                'quota' => 800,
                'enabled' => true,
            ],
            'birds' => [
                'label' => 'Birds',
                'noun' => 'Bird',
                'qid' => 'Q5113',
                'quota' => 1300,
                'enabled' => true,
            ],
            'reptiles' => [
                'label' => 'Reptiles',
                'noun' => 'Reptile',
                'qid' => 'Q10811',
                'quota' => 600,
                'enabled' => true,
            ],
            'amphibians' => [
                'label' => 'Amphibians',
                'noun' => 'Amphibian',
                'qid' => 'Q10908',
                'quota' => 350,
                'enabled' => true,
            ],
            'insects' => [
                'label' => 'Insects',
                'noun' => 'Insect',
                'qid' => 'Q1390',
                'quota' => 500,
                'enabled' => true,
            ],
            'arachnids' => [
                'label' => 'Arachnids',
                'noun' => 'Spider or arachnid',
                'qid' => 'Q1358',
                'quota' => 200,
                'enabled' => true,
            ],
            'other_invertebrates' => [
                'label' => 'Other invertebrates',
                'noun' => 'Invertebrate',
                'qid' => 'Q43806',
                'quota' => 0,
                'enabled' => false,
            ],
        ],

        'excluded_taxa' => [
            ['qid' => 'Q160', 'label' => 'Cetacea'],
            ['qid' => 'Q25431', 'label' => 'Sirenia'],
            ['qid' => 'Q219329', 'label' => 'Sea turtles'],
            ['qid' => 'Q460286', 'label' => 'Sea snakes'],
            ['qid' => 'Q11753180', 'label' => 'Laticaudinae'],
            ['qid' => 'Q152', 'label' => 'Fish'],
            ['qid' => 'Q27207', 'label' => 'Osteichthyes'],
            ['qid' => 'Q127282', 'label' => 'Actinopterygii'],
            ['qid' => 'Q25371', 'label' => 'Chondrichthyes'],
            ['qid' => 'Q194257', 'label' => 'Elasmobranchii'],
            ['qid' => 'Q149104', 'label' => 'Holocephali'],
            ['qid' => 'Q161095', 'label' => 'Agnatha'],
            ['qid' => 'Q15100334', 'label' => 'Myxini'],
            ['qid' => 'Q261485', 'label' => 'Petromyzontiformes'],
            ['qid' => 'Q160830', 'label' => 'Sarcopterygii'],
            ['qid' => 'Q130932', 'label' => 'Placodermi'],
            ['qid' => 'Q130955', 'label' => 'Acanthodii'],
        ],

        'iucn_statuses' => [
            'Q219127' => 'Critically Endangered',
            'Q11394' => 'Endangered',
            'Q96377276' => 'Endangered',
            'Q278113' => 'Vulnerable',
            'Q719675' => 'Near Threatened',
            'Q211005' => 'Least Concern',
            'Q3245245' => 'Data Deficient',
            'Q239509' => 'Extinct in the Wild',
            'Q237350' => 'Extinct',
        ],

        'unknown_iucn_status' => 'Check IUCN Red List',
    ],

    'open_vocabulary' => [
        'tier1_metadata_path' => 'public/data/animal-vocabulary.json',
        'tier1_bin_path' => 'public/data/animal-vocabulary.f16.bin',
        'tier1_url' => '/data/animal-vocabulary.json',
        'tier1_bin_url' => '/data/animal-vocabulary.f16.bin',
        'extended_metadata_path' => 'public/data/animal-vocabulary-extended.json',
        'extended_bin_path' => 'public/data/animal-vocabulary-extended.i8.bin',
        'extended_url' => '/data/animal-vocabulary-extended.json',
        'extended_bin_url' => '/data/animal-vocabulary-extended.i8.bin',
        'extended_max' => 20000,

        'templates' => [
            'a photo of a {name}',
            'a photo of a {name} in the wild',
            'a photo of a {name} in a zoo',
            'a close-up photo of a {name}',
        ],
        'extended_templates' => [
            'a photo of a {name}',
            'a photo of {scientific}, a {group_noun}',
        ],

        'view_weights' => [
            'whole' => 0.35,
            'crop_70' => 0.25,
            'crop_50' => 0.25,
            'box' => 0.15,
        ],

        'thresholds' => [
            'open_min_prob' => 0.25,
            'open_margin' => 0.08,
            'open_top_k' => 5,
            'extended_min_prob' => 0.35,
            'extended_margin' => 0.12,
            'candidate_floor' => 0.12,
        ],

        'out_of_scope' => [
            'fish',
            'shark',
            'whale',
            'dolphin',
            'sea turtle',
            'jellyfish',
            'coral',
            'octopus',
            'crab',
            'snail',
        ],
    ],

    'wikimedia' => [
        'user_agent' => env('WIKIMEDIA_USER_AGENT', 'EcoSight/1.0 (student project; +http://ecosight.test)'),
        'timeout_seconds' => 5,
        'cache_days' => 30,
        'license' => 'Text from Wikipedia, CC BY-SA 4.0',
        'wikipedia_summary_url' => 'https://en.wikipedia.org/api/rest_v1/page/summary/',
        'wikidata_entity_url' => 'https://www.wikidata.org/wiki/Special:EntityData/',
    ],

    'images' => [
        'max_bytes' => 4_194_304,
        'max_long_side' => 1200,
        'min_short_side' => 400,
        'candidate_ttl_minutes' => 15,
        'lookup_timeout_seconds' => 5,
        'download_timeout_seconds' => 15,
        'lookup_cache_minutes' => 60,
        'wikipedia_api' => 'https://en.wikipedia.org/w/api.php',
        'commons_api' => 'https://commons.wikimedia.org/w/api.php',
        'allowed_hosts' => [
            'en.wikipedia.org',
            'commons.wikimedia.org',
            'upload.wikimedia.org',
        ],
        'reject_terms' => [
            'map', 'range', 'distribution', 'diagram', 'drawing', 'illustration',
            'painting', 'skull', 'skeleton', 'taxidermy', 'specimen', 'sign',
            'chart', 'graph', 'locator', 'logo', 'icon', 'clipart', 'cartoon',
        ],
    ],


    'general_actions' => [
        'pool' => [
            'habitat' => [
                'title' => 'Support local habitat protection',
                'description' => 'Support local projects that protect and restore native habitat for wildlife.',
                'difficulty' => 'medium',
                'category' => 'habitat protection',
            ],
            'waste' => [
                'title' => 'Reduce plastic and food waste outdoors',
                'description' => 'Take waste home and dispose of it correctly so it cannot harm wildlife.',
                'difficulty' => 'easy',
                'category' => 'waste reduction',
            ],
            'pollinators' => [
                'title' => 'Protect pollinators',
                'description' => 'Leave native flowers and undisturbed nesting spots for bees, butterflies, and other pollinators.',
                'difficulty' => 'easy',
                'category' => 'habitat protection',
            ],
            'pesticides' => [
                'title' => 'Reduce pesticide use',
                'description' => 'Avoid unnecessary pesticides and choose wildlife-friendly alternatives where possible.',
                'difficulty' => 'medium',
                'category' => 'community',
            ],
            'corridors' => [
                'title' => 'Keep wildlife corridors open',
                'description' => 'Support connected green spaces so animals can move safely between feeding and resting areas.',
                'difficulty' => 'medium',
                'category' => 'habitat protection',
            ],
            'windows' => [
                'title' => 'Make windows bird-safe',
                'description' => 'Use visible markers or screens on large windows to reduce bird collisions.',
                'difficulty' => 'easy',
                'category' => 'habitat protection',
            ],
            'basking' => [
                'title' => 'Leave basking sites undisturbed',
                'description' => 'Avoid disturbing rocks, logs, and sunny edges where reptiles warm up and shelter.',
                'difficulty' => 'easy',
                'category' => 'habitat protection',
            ],
            'water' => [
                'title' => 'Protect clean water and damp habitat',
                'description' => 'Keep ponds, streams, and damp sheltered areas free from pollution and unnecessary disturbance.',
                'difficulty' => 'medium',
                'category' => 'habitat protection',
            ],
            'native-plants' => [
                'title' => 'Choose native plants',
                'description' => 'Plant local native species that provide food and shelter for wildlife.',
                'difficulty' => 'easy',
                'category' => 'habitat protection',
            ],
            'citizen-science' => [
                'title' => 'Share wildlife sightings',
                'description' => 'Report sightings to a local wildlife recording scheme to support conservation research.',
                'difficulty' => 'easy',
                'category' => 'community',
            ],
        ],

        'by_group' => [
            'default' => ['habitat', 'waste'],
            'mammals' => ['corridors', 'native-plants'],
            'birds' => ['windows', 'citizen-science'],
            'reptiles' => ['basking', 'habitat'],
            'amphibians' => ['water', 'native-plants'],
            'insects' => ['pollinators', 'pesticides'],
            'arachnids' => ['pollinators', 'pesticides'],
            'other_invertebrates' => ['habitat', 'pesticides'],
        ],
    ],
];

