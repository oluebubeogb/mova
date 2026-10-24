<?php
/**
 * Build the 9 setup-wizard kits (single copy, mova-plugins/mova-setup-wizard/kits).
 *
 *   php bin/build-kits.php        # writes kits/<id>/kit.json + kits/manifest.json
 *   php bin/capture.php kits      # real screenshot previews (kits/<id>/preview.png)
 *
 * Kits are lists of Mova\Theme\Sections specs with {{tokens}} filled in by the wizard:
 * site_name, tagline, about, city_or_area, email(_href), phone(_href), address, year.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
$kitsDir = $root . '/mova-plugins/mova-setup-wizard/kits';

$contactItems = [
    ['icon' => 'phone', 'label' => 'Call us', 'value' => '{{phone}}', 'href' => '{{phone_href}}'],
    ['icon' => 'mail', 'label' => 'Email', 'value' => '{{email}}', 'href' => '{{email_href}}'],
    ['icon' => 'pin', 'label' => 'Visit', 'value' => '{{address}}'],
];
$cta = static fn (string $t, string $lead, array $p, ?array $s = null, string $v = 'band') => ['type' => 'cta', 'variant' => $v, 'title' => $t, 'lead' => $lead, 'primary' => $p] + ($s ? ['secondary' => $s] : []);
$head = static fn (string $crumb, string $eyebrow, string $title, string $lead, string $v = 'soft', array $extra = []) => ['type' => 'pagehead', 'variant' => $v, 'crumb' => $crumb, 'eyebrow' => $eyebrow, 'title' => $title, 'lead' => $lead] + $extra;

/* ───────────────────────────── SCHOOL ───────────────────────────── */
function schoolPages(string $st, array $contactItems, callable $cta, callable $head): array
{
    $hero = [
        'classic' => ['type' => 'hero', 'variant' => 'split', 'eyebrow' => 'Admissions open for {{year}}', 'title' => 'Welcome to <em>{{site_name}}</em>', 'lead' => '{{tagline}}. {{about}}',
            'primary' => ['label' => 'Book a visit', 'href' => '/admissions'], 'secondary' => ['label' => 'Discover our school', 'href' => '/about'],
            'points' => ['Small class sizes', 'Specialist teachers', 'Rich clubs & sport'], 'art' => 'campus', 'badge' => ['icon' => 'award', 'title' => 'Outstanding', 'text' => 'pastoral care & results']],
        'modern' => ['type' => 'hero', 'variant' => 'gradient', 'eyebrow' => 'Admissions open for {{year}}', 'title' => 'Where curious minds <em>grow brilliant</em>', 'lead' => '{{site_name}} — {{tagline}}. {{about}}',
            'primary' => ['label' => 'Book a visit', 'href' => '/admissions'], 'secondary' => ['label' => 'Explore academics', 'href' => '/academics'],
            'stats' => [['value' => '1:14', 'label' => 'Teacher ratio'], ['value' => '40+', 'label' => 'Clubs & societies'], ['value' => '96%', 'label' => 'Parent satisfaction']]],
        'compact' => ['type' => 'hero', 'variant' => 'centered', 'eyebrow' => '{{tagline}}', 'title' => '{{site_name}}', 'lead' => '{{about}}',
            'primary' => ['label' => 'Apply now', 'href' => '/admissions'], 'secondary' => ['label' => 'Contact the office', 'href' => '/contact'], 'art' => 'campus'],
    ][$st];
    $pillars = ['type' => 'features', 'variant' => $st === 'modern' ? 'bento' : ($st === 'compact' ? 'minimal' : 'cards'), 'columns' => $st === 'compact' ? 4 : 3,
        'eyebrow' => 'Why families choose us', 'title' => 'An education that goes further', 'lead' => 'Every part of school life at {{site_name}} is designed to help children feel confident, capable and cared for.',
        'items' => [
            ['icon' => 'book', 'title' => 'Ambitious learning', 'text' => 'A broad, challenging curriculum taught by subject specialists who love what they teach.'],
            ['icon' => 'heart', 'title' => 'Pastoral care', 'text' => 'Every pupil has a tutor who knows them well and a team that puts wellbeing first.'],
            ['icon' => 'palette', 'title' => 'Arts & creativity', 'text' => 'Music, drama and art studios where talent is discovered and celebrated.'],
            ['icon' => 'trophy', 'title' => 'Sport for all', 'text' => 'Teams, fixtures and fitness for every ability — from first try to first team.'],
            ['icon' => 'globe', 'title' => 'Global outlook', 'text' => 'Languages, exchanges and service projects that open young minds to the world.'],
            ['icon' => 'users', 'title' => 'Strong community', 'text' => 'Parents, staff and alumni working together for every child.'],
        ]];
    if ($st === 'compact') {
        $pillars['items'] = array_slice($pillars['items'], 0, 4);
    }
    $stats = ['type' => 'stats', 'variant' => $st === 'classic' ? 'cards' : 'band', 'eyebrow' => 'At a glance', 'title' => 'A school that delivers',
        'items' => [['value' => '850', 'label' => 'Pupils', 'text' => 'Ages 3 to 18 on one campus.'], ['value' => '98%', 'label' => 'Exam pass rate', 'text' => 'Consistently above national average.'], ['value' => '40+', 'label' => 'Clubs', 'text' => 'Something for every interest.'], ['value' => '1:14', 'label' => 'Teacher ratio', 'text' => 'Real attention for every learner.']]];
    $quotes = ['type' => 'testimonials', 'variant' => $st === 'classic' ? 'spotlight' : 'grid', 'eyebrow' => 'Parent voices', 'title' => 'What our families say',
        'items' => [
            ['quote' => 'Our daughter has grown in confidence beyond anything we imagined. The teachers truly know her.', 'name' => 'Ngozi A.', 'role' => 'Parent, Year 5'],
            ['quote' => 'Warm, ambitious and wonderfully organised. Choosing {{site_name}} was the best decision we made.', 'name' => 'Michael T.', 'role' => 'Parent, Year 9'],
            ['quote' => 'The clubs and trips are incredible — my son comes home excited every single day.', 'name' => 'Fatima B.', 'role' => 'Parent, Year 7'],
        ]];
    if ($st === 'classic') {
        $quotes['items'] = [$quotes['items'][1]];
    }
    $events = ['type' => 'news', 'variant' => 'events', 'eyebrow' => 'Coming up', 'title' => 'Dates for your diary', 'more' => ['label' => 'Full calendar', 'href' => '/news'],
        'items' => [['day' => '14', 'month' => 'Nov', 'title' => 'Open morning & campus tours', 'time' => '09:30 – 12:00', 'place' => 'Main hall', 'href' => '/admissions'],
            ['day' => '28', 'month' => 'Nov', 'title' => 'Winter concert', 'time' => '18:00', 'place' => 'Performing arts centre', 'href' => '/news'],
            ['day' => '09', 'month' => 'Dec', 'title' => 'Parent–teacher conferences', 'time' => '15:30 – 19:00', 'place' => 'Classrooms', 'href' => '/news']]];
    $news = ['type' => 'news', 'variant' => 'cards', 'eyebrow' => 'Latest news', 'title' => 'Life at {{site_name}}', 'more' => ['label' => 'All news', 'href' => '/news'],
        'items' => [['tag' => 'Achievement', 'date' => 'This week', 'title' => 'Science team wins regional championship', 'text' => 'Our Year 10 team took first place with a project on clean water.', 'href' => '/news', 'art' => 'study'],
            ['tag' => 'Arts', 'date' => 'Last week', 'title' => 'A sell-out spring musical', 'text' => 'Three nights, 60 performers and a standing ovation.', 'href' => '/news', 'art' => 'stage'],
            ['tag' => 'Sport', 'date' => 'This month', 'title' => 'Under-14s reach the county final', 'text' => 'A brilliant season built on teamwork and determination.', 'href' => '/news', 'art' => 'sport']]];

    $home = [$hero];
    if ($st === 'modern') {
        $home[] = ['type' => 'logos', 'title' => 'Proud partners and accreditations', 'items' => [['name' => 'Cambridge Assessment'], ['name' => 'Duke of Edinburgh'], ['name' => 'British Council'], ['name' => 'Eco-Schools'], ['name' => 'STEM Learning'], ['name' => 'Arts Mark']]];
    }
    $home[] = $pillars;
    if ($st !== 'compact') {
        $home[] = ['type' => 'split', 'eyebrow' => 'Our approach', 'title' => 'Known, challenged and supported', 'paragraphs' => ['{{about}}', 'Small classes mean teachers can stretch every learner, spot challenges early and celebrate every success.'],
            'points' => ['Personal learning plans', 'Weekly progress updates for parents', 'Leadership opportunities for every pupil'], 'primary' => ['label' => 'About {{site_name}}', 'href' => '/about'], 'art' => 'study', 'tone' => $st === 'modern' ? 'soft' : 'plain'];
    }
    $home[] = $stats;
    $home[] = $quotes;
    $home[] = $st === 'compact' ? $events : $news;
    if ($st === 'modern') {
        $home[] = $events;
    }
    $home[] = $cta('Come and see us in action', 'Book a personal tour, meet our teachers and discover what makes {{site_name}} special.', ['label' => 'Book a visit', 'href' => '/admissions'], ['label' => 'Contact us', 'href' => '/contact'], $st === 'compact' ? 'card' : 'band');

    $about = [
        $head('About us', 'Our story', 'About {{site_name}}', '{{tagline}}. {{about}}', $st === 'modern' ? 'brand' : 'soft'),
        ['type' => 'split', 'eyebrow' => 'Our mission', 'title' => 'Inspiring every child to flourish', 'paragraphs' => ['We believe school should be a place of joy and ambition in equal measure. Our teachers combine high expectations with genuine warmth, so pupils feel safe to take risks and proud of what they achieve.', 'From the early years to sixth form, we help young people grow into thoughtful, resilient and kind adults ready for a changing world.'],
            'points' => ['Respect', 'Curiosity', 'Courage', 'Kindness'], 'art' => 'community'],
        ['type' => 'timeline', 'variant' => 'timeline', 'eyebrow' => 'Our journey', 'title' => 'Milestones along the way', 'items' => [
            ['meta' => 'Founded', 'title' => 'A small school with big ideas', 'text' => 'Opened with a handful of classrooms and a commitment to every child.'],
            ['meta' => 'Growing', 'title' => 'New science & arts wings', 'text' => 'Purpose-built labs, studios and a performing arts centre.'],
            ['meta' => 'Today', 'title' => 'A thriving community', 'text' => 'Hundreds of pupils, dedicated staff and a proud alumni network.'],
            ['meta' => '{{year}}', 'title' => 'Looking ahead', 'text' => 'Investing in digital learning, sustainability and wellbeing.']]],
        ['type' => 'team', 'eyebrow' => 'Leadership', 'title' => 'Meet our leadership team', 'lead' => 'Experienced educators who know every pupil by name.', 'items' => [
            ['name' => 'Dr. Adaeze Okonkwo', 'role' => 'Head of School', 'bio' => 'Twenty years in education and a passion for pupil voice.'],
            ['name' => 'James Whitfield', 'role' => 'Deputy Head, Academic', 'bio' => 'Leads curriculum, assessment and teacher development.'],
            ['name' => 'Maria Santos', 'role' => 'Head of Pastoral Care', 'bio' => 'Champion of wellbeing, inclusion and student support.'],
            ['name' => 'Samuel Boateng', 'role' => 'Director of Sport', 'bio' => 'Coaches teams and fitness for every ability.']]],
        $cta('Become part of our story', 'We would love to show you around.', ['label' => 'Book a visit', 'href' => '/admissions'], null, 'card'),
    ];
    $academics = [
        $head('Academics', 'Curriculum', 'Learning that lasts a lifetime', 'A broad, balanced and ambitious curriculum — taught by specialists and shaped around each learner.'),
        ['type' => 'features', 'variant' => 'cards', 'eyebrow' => 'Stages', 'title' => 'A clear path from first day to graduation', 'items' => [
            ['icon' => 'sparkles', 'title' => 'Early Years', 'text' => 'Play-based learning that builds language, numeracy and confidence.'],
            ['icon' => 'book', 'title' => 'Primary', 'text' => 'Strong foundations in literacy and maths with rich topic work.'],
            ['icon' => 'compass', 'title' => 'Secondary', 'text' => 'Specialist teaching across sciences, humanities, languages and arts.'],
            ['icon' => 'cap', 'title' => 'Sixth Form', 'text' => 'Exam pathways, mentoring and university guidance.'],
            ['icon' => 'flask', 'title' => 'STEM', 'text' => 'Labs, robotics and coding clubs that make ideas real.'],
            ['icon' => 'globe', 'title' => 'Languages', 'text' => 'French, Spanish and Mandarin from an early age.']]],
        ['type' => 'split', 'reverse' => true, 'eyebrow' => 'Beyond the classroom', 'title' => 'Clubs, trips and enrichment', 'paragraphs' => ['Over 40 clubs run every week — from debating and chess to coding, choir and climbing. Residential trips, fieldwork and service projects bring learning to life.'],
            'points' => ['Debate & model UN', 'Orchestra, choir & bands', 'Robotics & coding', 'Community service'], 'art' => 'stage'],
        ['type' => 'gallery', 'eyebrow' => 'Gallery', 'title' => 'A day in the life', 'items' => [['caption' => 'Science lab'], ['caption' => 'Library'], ['caption' => 'Art studio'], ['caption' => 'Sports day'], ['caption' => 'Music room'], ['caption' => 'Field trip']]],
        $cta('See our curriculum in action', 'Visit during a normal school day and sit in on lessons.', ['label' => 'Arrange a visit', 'href' => '/admissions']),
    ];
    $admissions = [
        $head('Admissions', 'Admissions {{year}}', 'Joining {{site_name}}', 'We welcome applications throughout the year. Here is how to get started — our admissions team is happy to help at every step.', 'soft', ['primary' => ['label' => 'Contact admissions', 'href' => '/contact']]),
        ['type' => 'timeline', 'variant' => 'steps', 'eyebrow' => 'How to apply', 'title' => 'Four simple steps', 'items' => [
            ['title' => 'Visit us', 'text' => 'Join an open morning or book a personal tour.'],
            ['title' => 'Apply', 'text' => 'Complete a short application form with your child’s details.'],
            ['title' => 'Meet', 'text' => 'A relaxed assessment and a conversation with our team.'],
            ['title' => 'Welcome', 'text' => 'Receive your offer and join our induction days.']]],
        ['type' => 'pricing', 'variant' => 'compact', 'eyebrow' => 'Fees', 'title' => 'Tuition and fees', 'lead' => 'Clear, all-inclusive termly fees. Sibling discounts and bursaries available.', 'plans' => [
            ['name' => 'Early Years', 'text' => 'Nursery & reception', 'price' => 'Contact us', 'per' => '', 'features' => ['Meals included', 'Extended day available'], 'cta' => ['label' => 'Enquire', 'href' => '/contact']],
            ['name' => 'Primary', 'text' => 'Years 1–6', 'price' => 'Contact us', 'per' => '', 'featured' => true, 'badge' => 'Most popular', 'features' => ['Clubs & trips included', 'Specialist music & PE'], 'cta' => ['label' => 'Enquire', 'href' => '/contact']],
            ['name' => 'Secondary', 'text' => 'Years 7–13', 'price' => 'Contact us', 'per' => '', 'features' => ['Exam fees included', 'University guidance'], 'cta' => ['label' => 'Enquire', 'href' => '/contact']]],
            'note' => 'Scholarships and means-tested bursaries are available — ask our admissions team.'],
        ['type' => 'faq', 'eyebrow' => 'FAQ', 'title' => 'Admissions questions', 'primary' => ['label' => 'Ask a question', 'href' => '/contact'], 'open_first' => true, 'items' => [
            ['q' => 'When can my child start?', 'a' => 'We accept pupils at the start of each term, and mid-term where places allow.'],
            ['q' => 'Is there an entrance assessment?', 'a' => 'A gentle, age-appropriate assessment helps us understand how best to support your child.'],
            ['q' => 'Do you offer transport?', 'a' => 'Yes — school buses serve several routes across {{city_or_area}}.'],
            ['q' => 'Are there bursaries?', 'a' => 'Means-tested bursaries and scholarships are available each year.']]],
        $cta('Ready to take the next step?', 'Our admissions team will guide you personally.', ['label' => 'Contact admissions', 'href' => '/contact'], null, 'card'),
    ];
    $newsPage = [
        $head('News & events', 'Stay connected', 'News & events', 'Celebrations, achievements and important dates from across the {{site_name}} community.'),
        $events, $news,
        ['type' => 'newsletter', 'title' => 'Get the weekly bulletin', 'lead' => 'News, dates and highlights delivered to your inbox every Friday.', 'button' => 'Subscribe'],
    ];
    $contact = [
        $head('Contact', 'Get in touch', 'We would love to hear from you', 'Questions about admissions, visits or school life? Our office team is here to help.'),
        ['type' => 'contact', 'title' => 'Contact the school office', 'address' => '{{address}}', 'items' => array_merge($contactItems, [['icon' => 'clock', 'label' => 'Office hours', 'value' => 'Mon–Fri, 7:30–16:30']])],
        ['type' => 'faq', 'title' => 'Quick answers', 'items' => [
            ['q' => 'How do I report an absence?', 'a' => 'Please call or email the school office before 9:00 on the day of absence.'],
            ['q' => 'Can I visit without an appointment?', 'a' => 'For safeguarding reasons, please book visits in advance with the office.'],
            ['q' => 'Who do I contact about fees?', 'a' => 'Our bursar’s office handles all fee questions — contact the office to be connected.']]],
    ];
    $pages = [
        ['slug' => 'home', 'title' => 'Home', 'role' => 'front', 'description' => '{{site_name}} — {{tagline}}. Discover our curriculum, admissions and school life.', 'sections' => $home],
        ['slug' => 'about', 'title' => 'About us', 'role' => 'about', 'description' => 'The story, values and leadership of {{site_name}}.', 'sections' => $about],
        ['slug' => 'academics', 'title' => 'Academics', 'role' => 'generic', 'description' => 'Curriculum, stages and enrichment at {{site_name}}.', 'sections' => $academics],
        ['slug' => 'admissions', 'title' => 'Admissions', 'role' => 'generic', 'description' => 'How to apply to {{site_name}}: visits, steps, fees and FAQs.', 'sections' => $admissions],
    ];
    if ($st !== 'compact') {
        $pages[] = ['slug' => 'news', 'title' => 'News & events', 'role' => 'generic', 'description' => 'Latest news and upcoming events at {{site_name}}.', 'sections' => $newsPage];
    }
    $pages[] = ['slug' => 'contact', 'title' => 'Contact', 'role' => 'contact', 'description' => 'Contact {{site_name}} — phone, email, address and office hours.', 'sections' => $contact];
    return $pages;
}

/* ─────────────────────────── ORGANIZATION ─────────────────────────── */
function orgPages(string $st, array $contactItems, callable $cta, callable $head): array
{
    $hero = [
        'classic' => ['type' => 'hero', 'variant' => 'split', 'eyebrow' => 'Community-led since day one', 'title' => '{{site_name}}', 'lead' => '{{tagline}}. {{about}}',
            'primary' => ['label' => 'Get involved', 'href' => '/get-involved'], 'secondary' => ['label' => 'Our work', 'href' => '/programs'],
            'points' => ['Locally led', 'Transparent', 'Volunteer powered'], 'art' => 'community', 'badge' => ['icon' => 'heart', 'title' => '12,000+', 'text' => 'people supported last year']],
        'modern' => ['type' => 'hero', 'variant' => 'gradient', 'eyebrow' => '{{site_name}}', 'title' => 'Real change starts <em>with community</em>', 'lead' => '{{tagline}}. {{about}}',
            'primary' => ['label' => 'Donate today', 'href' => '/get-involved'], 'secondary' => ['label' => 'See our impact', 'href' => '/programs'],
            'stats' => [['value' => '12k+', 'label' => 'People reached'], ['value' => '350', 'label' => 'Volunteers'], ['value' => '92p', 'label' => 'Of every £1 to programmes']]],
        'compact' => ['type' => 'hero', 'variant' => 'centered', 'eyebrow' => '{{tagline}}', 'title' => '{{site_name}}', 'lead' => '{{about}}',
            'primary' => ['label' => 'Get involved', 'href' => '/get-involved'], 'secondary' => ['label' => 'Contact us', 'href' => '/contact'], 'art' => 'community'],
    ][$st];
    $programs = ['type' => 'features', 'variant' => $st === 'modern' ? 'bento' : ($st === 'compact' ? 'minimal' : 'cards'), 'columns' => $st === 'compact' ? 4 : 3,
        'eyebrow' => 'What we do', 'title' => 'Programmes that make a difference', 'lead' => 'Practical, people-centred work across {{city_or_area}} — designed with the communities we serve.',
        'items' => [
            ['icon' => 'book', 'title' => 'Education & skills', 'text' => 'Tutoring, mentoring and training that open doors to opportunity.', 'href' => '/programs'],
            ['icon' => 'heart', 'title' => 'Health & wellbeing', 'text' => 'Outreach, support groups and access to care when it matters most.', 'href' => '/programs'],
            ['icon' => 'home', 'title' => 'Family support', 'text' => 'Food, advice and a friendly face for families facing hard times.', 'href' => '/programs'],
            ['icon' => 'leaf', 'title' => 'Greener neighbourhoods', 'text' => 'Community gardens, clean-ups and climate action led by residents.', 'href' => '/programs'],
            ['icon' => 'users', 'title' => 'Youth leadership', 'text' => 'Young people leading projects, finding their voice and their future.', 'href' => '/programs'],
        ]];
    if ($st === 'compact') {
        $programs['items'] = array_slice($programs['items'], 0, 4);
    }
    if ($st === 'classic') {
        $programs['items'][] = ['icon' => 'chat', 'title' => 'Advice & advocacy', 'text' => 'Free, confidential guidance on housing, benefits and rights.', 'href' => '/programs'];
    }
    $impact = ['type' => 'stats', 'variant' => $st === 'classic' ? 'cards' : 'band', 'eyebrow' => 'Our impact', 'title' => 'Last year, together we…',
        'items' => [['value' => '12,400', 'label' => 'People supported', 'text' => 'Across all programmes.'], ['value' => '38,000', 'label' => 'Meals shared', 'text' => 'Through our community kitchen.'], ['value' => '350', 'label' => 'Volunteers', 'text' => 'Giving 20,000+ hours.'], ['value' => '92%', 'label' => 'To programmes', 'text' => 'Of every pound raised.']]];
    $stories = ['type' => 'testimonials', 'variant' => $st === 'classic' ? 'spotlight' : 'grid', 'eyebrow' => 'Stories', 'title' => 'Voices from our community',
        'items' => [
            ['quote' => '{{site_name}} was there when my family needed it most. Now I volunteer every week to give something back.', 'name' => 'Grace O.', 'role' => 'Volunteer & former client'],
            ['quote' => 'The mentoring programme changed my son’s direction completely. He is now at college and thriving.', 'name' => 'David K.', 'role' => 'Parent'],
            ['quote' => 'A brilliant, trusted partner. Their reach into the community is unmatched.', 'name' => 'Aisha M.', 'role' => 'Partner organisation'],
        ]];
    if ($st === 'classic') {
        $stories['items'] = [$stories['items'][0]];
    }
    $news = ['type' => 'news', 'variant' => 'cards', 'eyebrow' => 'Latest', 'title' => 'News from the field', 'more' => ['label' => 'All updates', 'href' => '/about'],
        'items' => [['tag' => 'Impact', 'date' => 'This month', 'title' => 'Community kitchen serves its 100,000th meal', 'text' => 'A milestone made possible by volunteers and donors.', 'href' => '/programs', 'art' => 'community'],
            ['tag' => 'Event', 'date' => 'Coming soon', 'title' => 'Annual charity fun run', 'text' => 'Run, walk or cheer — every step funds local programmes.', 'href' => '/get-involved', 'art' => 'sport'],
            ['tag' => 'Partnership', 'date' => 'Last month', 'title' => 'New youth centre partnership', 'text' => 'Expanding after-school support to two more neighbourhoods.', 'href' => '/programs', 'art' => 'city']]];
    $home = [$hero];
    if ($st === 'modern') {
        $home[] = ['type' => 'logos', 'title' => 'Working alongside', 'items' => [['name' => 'City Council'], ['name' => 'Community Trust'], ['name' => 'Rotary Club'], ['name' => 'Health Board'], ['name' => 'Food Network'], ['name' => 'Youth Alliance']]];
    }
    $home[] = $programs;
    $home[] = $impact;
    if ($st !== 'compact') {
        $home[] = ['type' => 'split', 'eyebrow' => 'Who we are', 'title' => 'Rooted in {{city_or_area}}', 'paragraphs' => ['{{about}}', 'We listen first, act together and measure what matters — so every contribution creates real, lasting change.'],
            'points' => ['Independent and community-led', 'Audited accounts published every year', 'Safeguarding-first in everything we do'], 'primary' => ['label' => 'Our story', 'href' => '/about'], 'art' => 'people', 'tone' => $st === 'modern' ? 'soft' : 'plain'];
    }
    $home[] = $stories;
    $home[] = $news;
    $home[] = $cta('Your support changes lives', 'Give, volunteer or partner with us — every action helps our community thrive.', ['label' => 'Get involved', 'href' => '/get-involved'], ['label' => 'Talk to us', 'href' => '/contact'], $st === 'compact' ? 'card' : 'band');

    $about = [
        $head('About', 'Our story', 'About {{site_name}}', '{{tagline}}. {{about}}', $st === 'modern' ? 'brand' : 'soft'),
        ['type' => 'split', 'eyebrow' => 'Mission', 'title' => 'Building a fairer, kinder community', 'paragraphs' => ['We exist to make sure everyone in {{city_or_area}} has the support, skills and connections they need to thrive.', 'Our work is shaped by the people we serve, delivered by local staff and volunteers, and funded by a generous community of supporters.'],
            'points' => ['Dignity', 'Inclusion', 'Collaboration', 'Accountability'], 'art' => 'community'],
        ['type' => 'timeline', 'variant' => 'timeline', 'eyebrow' => 'Journey', 'title' => 'How we got here', 'items' => [
            ['meta' => 'The start', 'title' => 'Neighbours helping neighbours', 'text' => 'A small group of volunteers began with a weekly food table.'],
            ['meta' => 'Growing', 'title' => 'Registered as a charity', 'text' => 'Formal governance, our first staff and new programmes.'],
            ['meta' => 'Today', 'title' => 'Thousands supported', 'text' => 'Five programmes, hundreds of volunteers and trusted partners.'],
            ['meta' => '{{year}}', 'title' => 'The next chapter', 'text' => 'A new community hub and expanded youth work.']]],
        ['type' => 'team', 'eyebrow' => 'Our people', 'title' => 'Staff & trustees', 'items' => [
            ['name' => 'Chiamaka Eze', 'role' => 'Executive Director', 'bio' => 'Fifteen years in community development.'],
            ['name' => 'Peter Lindqvist', 'role' => 'Chair of Trustees', 'bio' => 'Governance, finance and strategy.'],
            ['name' => 'Yasmin Haddad', 'role' => 'Programmes Lead', 'bio' => 'Designs services with the community.'],
            ['name' => 'Kwame Mensah', 'role' => 'Volunteer Coordinator', 'bio' => 'Welcomes and supports every volunteer.']]],
        $cta('Join us', 'Be part of what happens next.', ['label' => 'Get involved', 'href' => '/get-involved'], null, 'card'),
    ];
    $programsPage = [
        $head('Programmes', 'Our work', 'Programmes & services', 'Free, practical support designed with — and for — our community.'),
        array_merge($programs, ['variant' => 'cards', 'columns' => 3, 'eyebrow' => '', 'title' => 'How we help', 'lead' => '']),
        ['type' => 'timeline', 'variant' => 'steps', 'eyebrow' => 'Getting support', 'title' => 'How to access our services', 'items' => [
            ['title' => 'Reach out', 'text' => 'Call, email or drop in — no referral needed.'],
            ['title' => 'Talk it through', 'text' => 'A friendly conversation about what you need.'],
            ['title' => 'Get connected', 'text' => 'We link you with the right programme or partner.']]],
        $impact,
        ['type' => 'gallery', 'eyebrow' => 'In pictures', 'title' => 'Our community in action', 'items' => [['caption' => 'Community kitchen'], ['caption' => 'Youth club'], ['caption' => 'Garden day'], ['caption' => 'Advice drop-in'], ['caption' => 'Fun run'], ['caption' => 'Volunteer training']]],
    ];
    $involved = [
        $head('Get involved', 'Make a difference', 'Get involved', 'Donate, volunteer, fundraise or partner with us — there is a way for everyone to help.', 'brand', ['primary' => ['label' => 'Contact us', 'href' => '/contact']]),
        ['type' => 'pricing', 'variant' => 'cards', 'eyebrow' => 'Give', 'title' => 'Choose your impact', 'lead' => 'Regular gifts help us plan ahead and reach more people.', 'plans' => [
            ['name' => 'Supporter', 'text' => 'Provides a week of meals for a family.', 'price' => '£10', 'per' => '/month', 'features' => ['Quarterly impact updates', 'Supporter newsletter'], 'cta' => ['label' => 'Give monthly', 'href' => '/contact']],
            ['name' => 'Champion', 'text' => 'Funds a term of mentoring for a young person.', 'price' => '£25', 'per' => '/month', 'featured' => true, 'badge' => 'Most impact', 'features' => ['Everything in Supporter', 'Invitations to project visits', 'Annual impact report'], 'cta' => ['label' => 'Become a champion', 'href' => '/contact']],
            ['name' => 'Partner', 'text' => 'For businesses and foundations.', 'price' => 'Let’s talk', 'per' => '', 'features' => ['Employee volunteering', 'Co-branded projects', 'Impact reporting'], 'cta' => ['label' => 'Partner with us', 'href' => '/contact']]]],
        ['type' => 'features', 'variant' => 'minimal', 'columns' => 3, 'eyebrow' => 'Volunteer', 'title' => 'Give your time', 'items' => [
            ['icon' => 'utensils', 'title' => 'Community kitchen', 'text' => 'Cook, serve and chat — weekday lunchtimes.'],
            ['icon' => 'book', 'title' => 'Mentoring', 'text' => 'Support a young person for one hour a week.'],
            ['icon' => 'calendar', 'title' => 'Events', 'text' => 'Help run fundraisers and community days.']]],
        ['type' => 'faq', 'title' => 'Common questions', 'items' => [
            ['q' => 'Do I need experience to volunteer?', 'a' => 'No — we provide training and support. Enthusiasm is all you need.'],
            ['q' => 'How is my donation used?', 'a' => '92% of every pound goes directly to programmes. We publish audited accounts every year.'],
            ['q' => 'Can my company get involved?', 'a' => 'Yes — we offer team volunteering days and partnership packages.']]],
        ['type' => 'newsletter', 'title' => 'Stay connected', 'lead' => 'Monthly stories and ways to help — straight to your inbox.', 'button' => 'Subscribe'],
    ];
    $contact = [
        $head('Contact', 'Say hello', 'Contact {{site_name}}', 'Need support, want to help or have a question? We are here for you.'),
        ['type' => 'contact', 'title' => 'Reach our team', 'address' => '{{address}}', 'items' => array_merge($contactItems, [['icon' => 'clock', 'label' => 'Open', 'value' => 'Mon–Fri, 9:00–17:00']])],
        $cta('Want to support our work?', 'Find the best way for you to get involved.', ['label' => 'Get involved', 'href' => '/get-involved'], null, 'card'),
    ];
    $pages = [
        ['slug' => 'home', 'title' => 'Home', 'role' => 'front', 'description' => '{{site_name}} — {{tagline}}. Programmes, impact and ways to get involved.', 'sections' => $home],
        ['slug' => 'about', 'title' => 'About', 'role' => 'about', 'description' => 'Mission, story and people behind {{site_name}}.', 'sections' => $about],
        ['slug' => 'programs', 'title' => 'Programmes', 'role' => 'generic', 'description' => 'Programmes and services offered by {{site_name}}.', 'sections' => $programsPage],
        ['slug' => 'get-involved', 'title' => 'Get involved', 'role' => 'generic', 'description' => 'Donate, volunteer or partner with {{site_name}}.', 'sections' => $involved],
        ['slug' => 'contact', 'title' => 'Contact', 'role' => 'contact', 'description' => 'Contact {{site_name}}.', 'sections' => $contact],
    ];
    return $pages;
}

/* ───────────────────────────── BUSINESS / BASIC ───────────────────────────── */
function basicPages(string $st, array $contactItems, callable $cta, callable $head): array
{
    $hero = [
        'classic' => ['type' => 'hero', 'variant' => 'split', 'eyebrow' => 'Welcome to {{site_name}}', 'title' => '{{tagline}}', 'lead' => '{{about}}',
            'primary' => ['label' => 'Get in touch', 'href' => '/contact'], 'secondary' => ['label' => 'Our services', 'href' => '/services'],
            'points' => ['Free consultation', 'Clear pricing', 'Friendly experts'], 'art' => 'product', 'badge' => ['icon' => 'star', 'title' => '5.0 rating', 'text' => 'from happy clients']],
        'modern' => ['type' => 'hero', 'variant' => 'gradient', 'eyebrow' => '{{site_name}}', 'title' => 'Great work, <em>made simple</em>', 'lead' => '{{tagline}}. {{about}}',
            'primary' => ['label' => 'Start a project', 'href' => '/contact'], 'secondary' => ['label' => 'See how it works', 'href' => '#how'],
            'stats' => [['value' => '250+', 'label' => 'Projects delivered'], ['value' => '4.9/5', 'label' => 'Client rating'], ['value' => '48h', 'label' => 'Average response']]],
        'compact' => ['type' => 'hero', 'variant' => 'centered', 'eyebrow' => '{{tagline}}', 'title' => '{{site_name}}', 'lead' => '{{about}}',
            'primary' => ['label' => 'Get a quote', 'href' => '/contact'], 'secondary' => ['label' => 'Pricing', 'href' => '/pricing'], 'art' => 'abstract'],
    ][$st];
    $services = ['type' => 'features', 'variant' => $st === 'modern' ? 'bento' : ($st === 'compact' ? 'minimal' : 'cards'), 'columns' => $st === 'compact' ? 4 : 3,
        'eyebrow' => 'Services', 'title' => 'How we can help', 'lead' => 'Focused services, delivered by people who care about the details.',
        'items' => [
            ['icon' => 'compass', 'title' => 'Strategy', 'text' => 'Clear goals, honest advice and a plan you can act on.', 'href' => '/services'],
            ['icon' => 'palette', 'title' => 'Design', 'text' => 'Beautiful, on-brand work that people remember.', 'href' => '/services'],
            ['icon' => 'zap', 'title' => 'Delivery', 'text' => 'Fast, reliable execution with no surprises.', 'href' => '/services'],
            ['icon' => 'chart', 'title' => 'Growth', 'text' => 'Measure what matters and keep improving.', 'href' => '/services'],
            ['icon' => 'chat', 'title' => 'Support', 'text' => 'Real people who answer quickly and solve problems.', 'href' => '/contact'],
            ['icon' => 'shield', 'title' => 'Peace of mind', 'text' => 'Transparent pricing, contracts and guarantees.', 'href' => '/pricing'],
        ]];
    if ($st === 'compact') {
        $services['items'] = array_slice($services['items'], 0, 4);
    } elseif ($st === 'modern') {
        $services['items'] = array_slice($services['items'], 0, 5);
    }
    $steps = ['type' => 'timeline', 'variant' => 'steps', 'anchor' => 'how', 'eyebrow' => 'How it works', 'title' => 'From first call to finished work', 'items' => [
        ['title' => 'Tell us what you need', 'text' => 'A short, free call to understand your goals.'],
        ['title' => 'Get a clear proposal', 'text' => 'Scope, timeline and a fixed price — no surprises.'],
        ['title' => 'We deliver', 'text' => 'Regular updates and a result you will love.']]];
    $quotes = ['type' => 'testimonials', 'variant' => $st === 'classic' ? 'spotlight' : 'grid', 'eyebrow' => 'Reviews', 'title' => 'Clients love working with us',
        'items' => [
            ['quote' => '{{site_name}} made the whole process effortless. Professional, friendly and brilliant results.', 'name' => 'Sophie L.', 'role' => 'Founder, Bloom Studio'],
            ['quote' => 'Clear communication, fair pricing and delivered ahead of schedule. Highly recommended.', 'name' => 'Emeka N.', 'role' => 'Operations Manager'],
            ['quote' => 'They genuinely care about getting it right. We will be back for our next project.', 'name' => 'Laura P.', 'role' => 'Marketing Lead'],
        ]];
    if ($st === 'classic') {
        $quotes['items'] = [$quotes['items'][0]];
    }
    $plans = [
        ['name' => 'Starter', 'text' => 'For small projects and quick wins.', 'price' => '$490', 'price_b' => '$4,900', 'per' => '/project', 'per_b' => '/year', 'features' => ['Discovery call', 'One deliverable', '2 rounds of revisions'], 'cta' => ['label' => 'Get started', 'href' => '/contact']],
        ['name' => 'Professional', 'text' => 'Our most popular package.', 'price' => '$1,490', 'price_b' => '$14,900', 'per' => '/project', 'per_b' => '/year', 'featured' => true, 'badge' => 'Most popular', 'features' => ['Everything in Starter', 'Full strategy session', 'Priority scheduling', 'Unlimited revisions'], 'cta' => ['label' => 'Choose Professional', 'href' => '/contact']],
        ['name' => 'Partner', 'text' => 'Ongoing support for growing teams.', 'price' => 'Custom', 'price_b' => 'Custom', 'per' => '', 'per_b' => '', 'features' => ['Dedicated contact', 'Monthly reporting', 'Retainer pricing'], 'cta' => ['label' => 'Talk to us', 'href' => '/contact']],
    ];
    $home = [$hero];
    if ($st === 'modern') {
        $home[] = ['type' => 'logos', 'title' => 'Trusted by teams at', 'items' => [['name' => 'Northwind'], ['name' => 'Lumen'], ['name' => 'Evergreen'], ['name' => 'Polaris'], ['name' => 'Harbor'], ['name' => 'Summit']]];
    }
    $home[] = $services;
    if ($st !== 'compact') {
        $home[] = ['type' => 'split', 'eyebrow' => 'About us', 'title' => 'Small team, serious craft', 'paragraphs' => ['{{about}}', 'We keep our client list small on purpose — so every project gets senior attention from start to finish.'],
            'points' => ['Senior experts on every project', 'Fixed, transparent pricing', 'Based in {{city_or_area}}, working everywhere'], 'primary' => ['label' => 'More about us', 'href' => '/about'], 'art' => 'people', 'tone' => $st === 'modern' ? 'soft' : 'plain'];
    }
    $home[] = $steps;
    $home[] = ['type' => 'stats', 'variant' => $st === 'classic' ? 'cards' : 'band', 'title' => 'Numbers we are proud of', 'items' => [['value' => '250+', 'label' => 'Projects', 'text' => 'Delivered on time.'], ['value' => '98%', 'label' => 'Happy clients', 'text' => 'Would recommend us.'], ['value' => '12', 'label' => 'Years', 'text' => 'Of experience.'], ['value' => '48h', 'label' => 'Response', 'text' => 'Or faster.']]];
    $home[] = $quotes;
    if ($st === 'modern') {
        $home[] = ['type' => 'pricing', 'variant' => 'toggle', 'eyebrow' => 'Pricing', 'title' => 'Simple, honest pricing', 'save' => 'Best value', 'plans' => $plans];
    }
    $home[] = $cta('Let’s build something great', 'Tell us about your project — we reply within one business day.', ['label' => 'Start a conversation', 'href' => '/contact'], null, $st === 'compact' ? 'card' : 'band');

    $about = [
        $head('About', 'Our story', 'About {{site_name}}', '{{tagline}}. {{about}}', $st === 'modern' ? 'brand' : 'soft'),
        ['type' => 'split', 'eyebrow' => 'What drives us', 'title' => 'Quality, honesty and care', 'paragraphs' => ['We started {{site_name}} because we believed clients deserved better: clear advice, fair prices and work that is genuinely excellent.', 'That belief still guides every project we take on.'],
            'points' => ['We say what we will do — then do it', 'We treat your budget like our own', 'We are here long after launch'], 'art' => 'study'],
        ['type' => 'team', 'eyebrow' => 'Team', 'title' => 'The people behind the work', 'items' => [
            ['name' => 'Olivia Grant', 'role' => 'Founder & Director', 'bio' => 'Leads strategy and client partnerships.'],
            ['name' => 'Tunde Bakare', 'role' => 'Head of Delivery', 'bio' => 'Keeps every project on time and on budget.'],
            ['name' => 'Mei Chen', 'role' => 'Lead Designer', 'bio' => 'Turns ideas into beautiful, useful work.'],
            ['name' => 'Lucas Moreau', 'role' => 'Client Success', 'bio' => 'Your first point of contact, always.']]],
        $quotes,
        $cta('Work with us', 'Let’s talk about what you need.', ['label' => 'Get in touch', 'href' => '/contact'], null, 'card'),
    ];
    $servicesPage = [
        $head('Services', 'What we do', 'Services', 'Everything you need to plan, launch and grow — delivered by one dedicated team.'),
        array_merge($services, ['variant' => 'cards', 'columns' => 3, 'eyebrow' => '', 'title' => 'Our services', 'lead' => '']),
        array_merge($steps, ['anchor' => '']),
        ['type' => 'faq', 'eyebrow' => 'FAQ', 'title' => 'Questions, answered', 'primary' => ['label' => 'Ask us anything', 'href' => '/contact'], 'open_first' => true, 'items' => [
            ['q' => 'How quickly can you start?', 'a' => 'Most projects begin within one to two weeks of agreeing the proposal.'],
            ['q' => 'Do you work with small businesses?', 'a' => 'Absolutely — many of our favourite clients are small, ambitious teams.'],
            ['q' => 'What does it cost?', 'a' => 'See our pricing page for packages, or ask for a fixed quote.'],
            ['q' => 'Do you offer ongoing support?', 'a' => 'Yes — our Partner plan includes ongoing support and reporting.']]],
        $cta('Not sure what you need?', 'Book a free 20-minute call and we will point you in the right direction.', ['label' => 'Book a call', 'href' => '/contact']),
    ];
    $pricing = [
        $head('Pricing', 'Pricing', 'Clear prices, no surprises', 'Choose a package or ask us for a custom quote.'),
        ['type' => 'pricing', 'variant' => $st === 'compact' ? 'compact' : 'cards', 'title' => 'Packages', 'plans' => $plans, 'note' => 'All prices exclude tax. Custom quotes available on request.'],
        ['type' => 'faq', 'title' => 'Pricing FAQ', 'items' => [
            ['q' => 'Are there any hidden fees?', 'a' => 'Never. Your proposal lists everything included.'],
            ['q' => 'How do payments work?', 'a' => 'Typically 50% to start and 50% on completion.'],
            ['q' => 'Can I change package later?', 'a' => 'Of course — upgrade at any time and we will credit what you have paid.']]],
        $cta('Need something custom?', 'We are happy to tailor a package to your needs.', ['label' => 'Request a quote', 'href' => '/contact'], null, 'card'),
    ];
    $contact = [
        $head('Contact', 'Contact', 'Let’s talk', 'Tell us about your project. We reply within one business day.'),
        ['type' => 'contact', 'title' => 'Get in touch', 'address' => '{{address}}', 'items' => array_merge($contactItems, [['icon' => 'clock', 'label' => 'Hours', 'value' => 'Mon–Fri, 9:00–17:30']])],
        ['type' => 'newsletter', 'title' => 'Ideas in your inbox', 'lead' => 'Occasional tips, news and offers. No spam, ever.', 'button' => 'Subscribe'],
    ];
    $pages = [
        ['slug' => 'home', 'title' => 'Home', 'role' => 'front', 'description' => '{{site_name}} — {{tagline}}.', 'sections' => $home],
        ['slug' => 'about', 'title' => 'About', 'role' => 'about', 'description' => 'About {{site_name}}: our story, values and team.', 'sections' => $about],
        ['slug' => 'services', 'title' => 'Services', 'role' => 'generic', 'description' => 'Services offered by {{site_name}}.', 'sections' => $servicesPage],
    ];
    if ($st !== 'modern') {
        $pages[] = ['slug' => 'pricing', 'title' => 'Pricing', 'role' => 'generic', 'description' => 'Packages and pricing at {{site_name}}.', 'sections' => $pricing];
    }
    $pages[] = ['slug' => 'contact', 'title' => 'Contact', 'role' => 'contact', 'description' => 'Contact {{site_name}}.', 'sections' => $contact];
    return $pages;
}

$meta = [
    'school-classic' => ['school', 'classic', 'Classic campus', 'Timeless, trustworthy layout with a split hero, spotlight quote and calm cards.', ['primary' => '#1e3a8a', 'accent' => '#b45309', 'secondary' => '#0f766e']],
    'school-modern' => ['school', 'modern', 'Modern campus', 'Bold gradient hero, bento features, live stats and an events calendar.', ['primary' => '#4f46e5', 'accent' => '#06b6d4', 'secondary' => '#f59e0b']],
    'school-compact' => ['school', 'compact', 'Compact campus', 'Tight, mobile-first layout for busy parents — key info in fewer pages.', ['primary' => '#0f766e', 'accent' => '#f59e0b', 'secondary' => '#1e3a8a']],
    'org-classic' => ['organization', 'classic', 'Classic mission', 'Warm, credible layout that puts your mission and people first.', ['primary' => '#166534', 'accent' => '#ca8a04', 'secondary' => '#7c2d12']],
    'org-modern' => ['organization', 'modern', 'Modern impact', 'Vivid gradient hero, impact numbers and donation tiers that convert.', ['primary' => '#be185d', 'accent' => '#7c3aed', 'secondary' => '#f97316']],
    'org-compact' => ['organization', 'compact', 'Compact community', 'Simple, focused pages for small charities and clubs.', ['primary' => '#0369a1', 'accent' => '#16a34a', 'secondary' => '#f59e0b']],
    'basic-classic' => ['generic', 'classic', 'Classic business', 'Professional split hero, services and a featured client quote.', ['primary' => '#1f2937', 'accent' => '#c2410c', 'secondary' => '#0e7490']],
    'basic-modern' => ['generic', 'modern', 'Modern studio', 'Gradient hero, bento services, logo strip and toggle pricing.', ['primary' => '#2563eb', 'accent' => '#7c3aed', 'secondary' => '#06b6d4']],
    'basic-compact' => ['generic', 'compact', 'Compact one-pager+', 'Lean, fast and to the point — perfect for freelancers and shops.', ['primary' => '#0f172a', 'accent' => '#10b981', 'secondary' => '#6366f1']],
];
$demo = [
    'school' => ['site_name' => 'Brookfield Academy', 'tagline' => 'Curious minds, kind hearts, bright futures', 'city' => 'Lagos', 'email' => 'admissions@brookfield.example', 'phone' => '+234 800 123 4567', 'address' => '12 Unity Road, Ikeja, Lagos'],
    'organization' => ['site_name' => 'Harbour Community Trust', 'tagline' => 'Together we build stronger communities', 'city' => 'Bristol', 'email' => 'hello@harbourtrust.example', 'phone' => '+44 117 000 0000', 'address' => '8 Quay Street, Bristol'],
    'generic' => ['site_name' => 'Northlight Studio', 'tagline' => 'Thoughtful work, beautifully delivered', 'city' => 'Austin', 'email' => 'hello@northlight.example', 'phone' => '+1 512 000 0000', 'address' => '400 Congress Ave, Austin, TX'],
];

$manifest = ['schema' => 2, 'name' => 'Mova Site Kits', 'version' => '2.0.0', 'updated_at' => date('Y-m-d'), 'kits' => []];
foreach ($meta as $id => [$pack, $style, $label, $desc, $palette]) {
    $pages = match ($pack) {
        'school' => schoolPages($style, $contactItems, $cta, $head),
        'organization' => orgPages($style, $contactItems, $cta, $head),
        default => basicPages($style, $contactItems, $cta, $head),
    };
    $nav = [];
    foreach ($pages as $p) {
        $nav[] = ['label' => $p['title'], 'url' => $p['slug'] === 'home' ? '/' : '/' . $p['slug']];
    }
    $navCta = match ($pack) {
        'school' => ['label' => 'Apply now', 'url' => '/admissions'],
        'organization' => ['label' => 'Get involved', 'url' => '/get-involved'],
        default => ['label' => 'Get in touch', 'url' => '/contact'],
    };
    $explore = array_values(array_filter($nav, static fn ($n) => $n['url'] !== '/' && $n['url'] !== '/contact'));
    $footer = [
        ['title' => '{{site_name}}', 'text' => '{{tagline}}.'],
        ['title' => 'Explore', 'links' => $explore],
        ['title' => 'Contact', 'links' => [['label' => '{{email}}', 'url' => '{{email_href}}'], ['label' => '{{phone}}', 'url' => '{{phone_href}}'], ['label' => 'Contact us', 'url' => '/contact']]],
    ];
    $kit = [
        'id' => $id, 'pack' => $pack, 'style' => $style, 'label' => $label, 'description' => $desc, 'version' => '2.0.0', 'schema' => 2,
        'tokens' => ['site_name', 'tagline', 'about', 'city_or_area', 'email', 'phone', 'address', 'year'],
        'palette' => $palette, 'demo' => $demo[$pack],
        'preview' => 'preview.png', 'preview_mobile' => 'preview-mobile.png',
        'nav' => $nav, 'nav_cta' => $navCta, 'footer_columns' => $footer, 'pages' => $pages,
    ];
    $dir = $kitsDir . '/' . $id;
    @mkdir($dir, 0775, true);
    // Clean v1 files (HTML pages, kit css/js, svg preview)
    foreach (['pages', 'assets'] as $sub) {
        if (is_dir($dir . '/' . $sub)) {
            array_map('unlink', glob($dir . '/' . $sub . '/*') ?: []);
            @rmdir($dir . '/' . $sub);
        }
    }
    @unlink($dir . '/preview.svg');
    file_put_contents($dir . '/kit.json', json_encode($kit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    $manifest['kits'][] = ['id' => $id, 'pack' => $pack, 'style' => $style, 'label' => $label, 'description' => $desc, 'version' => '2.0.0',
        'preview' => $id . '/preview.png', 'preview_mobile' => $id . '/preview-mobile.png', 'palette' => $palette, 'pages' => array_column($pages, 'title'), 'path' => $id];
}
file_put_contents($kitsDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo count($manifest['kits']) . " kits written\n";
