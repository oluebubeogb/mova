<?php
/**
 * Mova section library catalogue (demo content).
 *
 * Each entry is rendered by Sections::render() into resources/templates/sections/<id>/
 * by bin/build-sections.php, and thumbnailed with headless Chromium by bin/capture.php.
 * Kits and the setup wizard reuse the same section types with their own copy.
 */

namespace Mova\Theme;

final class SectionLibrary
{
    public const CATEGORIES = [
        'hero' => 'Heroes', 'stats' => 'Stats', 'features' => 'Features', 'content' => 'Content',
        'testimonials' => 'Testimonials', 'team' => 'Team', 'faq' => 'FAQ', 'gallery' => 'Gallery',
        'steps' => 'Steps & timeline', 'logos' => 'Logos', 'cta' => 'Call to action', 'contact' => 'Contact',
        'news' => 'News & events', 'newsletter' => 'Newsletter', 'pricing' => 'Pricing',
    ];

    /** @return list<array{id:string,name:string,category:string,tags:list<string>,description:string,spec:array}> */
    public static function all(): array
    {
        $go = ['label' => 'Get started', 'href' => '/contact'];
        $more = ['label' => 'See how it works', 'href' => '#how'];
        $L = [];

        // ── Heroes ─────────────────────────────────────────────
        $L[] = ['hero-gradient', 'Gradient hero', 'hero', ['hero', 'gradient', 'stats', 'bold'],
            'Bold brand-gradient hero with eyebrow pill, two actions and a stat row.',
            ['type' => 'hero', 'variant' => 'gradient', 'eyebrow' => 'New for 2026',
             'title' => 'Build something <em>people remember</em>',
             'lead' => 'A calm, fast and beautiful home for your brand — designed to convert visitors into customers from the first scroll.',
             'primary' => $go, 'secondary' => $more,
             'stats' => [['value' => '12k+', 'label' => 'Happy customers'], ['value' => '4.9/5', 'label' => 'Average rating'], ['value' => '24/7', 'label' => 'Support']]]];
        $L[] = ['hero-split', 'Split hero with image', 'hero', ['hero', 'split', 'image', 'badge'],
            'Copy on the left, framed image on the right with a floating highlight card.',
            ['type' => 'hero', 'variant' => 'split', 'eyebrow' => 'Trusted by 300+ teams',
             'title' => 'The smarter way to run your day',
             'lead' => 'Plan, share and deliver in one place. Everything your team needs, nothing it does not.',
             'primary' => $go, 'secondary' => ['label' => 'Book a demo', 'href' => '/contact'],
             'points' => ['Free 14-day trial', 'No credit card', 'Cancel any time'],
             'art' => 'people', 'badge' => ['icon' => 'star', 'title' => 'Rated 4.9', 'text' => 'by 2,000+ reviews']]];
        $L[] = ['hero-centered', 'Centered hero', 'hero', ['hero', 'centered', 'wide image', 'minimal'],
            'Centered headline and actions above a cinematic 21:9 visual.',
            ['type' => 'hero', 'variant' => 'centered', 'eyebrow' => 'Welcome',
             'title' => 'Design-led spaces for modern living',
             'lead' => 'We plan, build and furnish homes that feel effortless — on time and on budget.',
             'primary' => ['label' => 'View projects', 'href' => '/projects'], 'secondary' => ['label' => 'Our story', 'href' => '/about'],
             'art' => 'city']];

        // ── Stats ──────────────────────────────────────────────
        $L[] = ['stats-band', 'Stats band', 'stats', ['stats', 'counter', 'dark', 'animated'],
            'Dark band with animated count-up numbers.',
            ['type' => 'stats', 'variant' => 'band', 'eyebrow' => 'By the numbers', 'title' => 'Results that speak for themselves',
             'items' => [['value' => '98%', 'label' => 'Client retention'], ['value' => '250+', 'label' => 'Projects delivered'], ['value' => '15', 'label' => 'Years of craft'], ['value' => '40k', 'label' => 'Community members']]]];
        $L[] = ['stats-cards', 'Stat cards', 'stats', ['stats', 'counter', 'cards', 'light'],
            'Light stat cards with supporting text.',
            ['type' => 'stats', 'variant' => 'cards', 'title' => 'Growing every year',
             'items' => [['value' => '3.2x', 'label' => 'Faster onboarding', 'text' => 'Compared with the industry average.'], ['value' => '120k', 'label' => 'Monthly visitors', 'text' => 'Across web and mobile.'], ['value' => '99.9%', 'label' => 'Uptime', 'text' => 'Backed by our service promise.']]]];

        // ── Features ───────────────────────────────────────────
        $feat = [
            ['icon' => 'zap', 'title' => 'Lightning fast', 'text' => 'Pages load in a blink on any device, so visitors stay and explore.'],
            ['icon' => 'shield', 'title' => 'Secure by default', 'text' => 'Modern security practices and automatic backups keep you safe.'],
            ['icon' => 'palette', 'title' => 'On-brand design', 'text' => 'Your colours, fonts and logo flow through every page automatically.'],
            ['icon' => 'chart', 'title' => 'Clear insights', 'text' => 'Understand what works with simple, privacy-friendly analytics.'],
            ['icon' => 'chat', 'title' => 'Human support', 'text' => 'Real people who answer quickly and actually solve the problem.'],
            ['icon' => 'globe', 'title' => 'Reach everyone', 'text' => 'Accessible, mobile-first and search-engine friendly out of the box.'],
        ];
        $L[] = ['features-cards', 'Feature cards', 'features', ['features', 'icons', 'grid', 'cards'],
            'Three-column grid of icon cards with hover lift.',
            ['type' => 'features', 'variant' => 'cards', 'eyebrow' => 'Why choose us', 'title' => 'Everything you need, beautifully done', 'lead' => 'Thoughtful details that make a real difference every day.', 'items' => $feat]];
        $L[] = ['features-bento', 'Bento features', 'features', ['features', 'icons', 'bento', 'modern'],
            'Bento layout with a wide lead card.',
            ['type' => 'features', 'variant' => 'bento', 'eyebrow' => 'Platform', 'title' => 'One platform. Endless possibilities.', 'items' => array_slice($feat, 0, 5)]];
        $L[] = ['features-minimal', 'Minimal features', 'features', ['features', 'icons', 'minimal', 'four columns'],
            'Borderless four-column feature list.',
            ['type' => 'features', 'variant' => 'minimal', 'columns' => 4, 'title' => 'Built around you', 'items' => array_slice($feat, 0, 4)]];

        // ── Content ────────────────────────────────────────────
        $L[] = ['split-story', 'Image + text', 'content', ['about', 'split', 'image', 'checklist'],
            'Story block with checklist and image; flip with "reverse".',
            ['type' => 'split', 'eyebrow' => 'Our story', 'title' => 'Small team, big ambitions',
             'paragraphs' => ['We started with a simple idea: great work should feel effortless for the people who receive it.', 'Today we partner with organisations of every size to make that idea real.'],
             'points' => ['Independent and founder-led', 'Carbon-neutral since 2021', 'Clients in 18 countries'],
             'primary' => ['label' => 'Meet the team', 'href' => '/about'], 'art' => 'community']];

        // ── Testimonials ───────────────────────────────────────
        $quotes = [
            ['quote' => 'The most polished launch we have ever had. Our enquiries doubled in the first month.', 'name' => 'Amara Okafor', 'role' => 'Founder, Lumen Studio'],
            ['quote' => 'Clear, fast and genuinely helpful. It feels like they are part of our team.', 'name' => 'Daniel Reyes', 'role' => 'Operations Lead, Northwind'],
            ['quote' => 'Every detail was considered. Our customers notice the difference.', 'name' => 'Priya Shah', 'role' => 'Director, Bloom & Co'],
        ];
        $L[] = ['testimonials-grid', 'Testimonial cards', 'testimonials', ['testimonials', 'reviews', 'stars', 'grid'],
            'Three review cards with star ratings and avatars.',
            ['type' => 'testimonials', 'variant' => 'grid', 'eyebrow' => 'Testimonials', 'title' => 'Loved by people like you', 'items' => $quotes]];
        $L[] = ['testimonials-spotlight', 'Spotlight quote', 'testimonials', ['testimonials', 'quote', 'large', 'spotlight'],
            'One large, centred quote on a soft tint.',
            ['type' => 'testimonials', 'variant' => 'spotlight', 'items' => [$quotes[0]]]];

        // ── Team ───────────────────────────────────────────────
        $L[] = ['team-grid', 'Team grid', 'team', ['team', 'people', 'staff', 'grid'],
            'Four-column team cards with gradient portrait placeholders.',
            ['type' => 'team', 'eyebrow' => 'Our people', 'title' => 'Meet the team', 'lead' => 'Experienced, curious and always on your side.',
             'items' => [['name' => 'Grace Mensah', 'role' => 'Managing Director', 'bio' => 'Twenty years leading creative teams.'], ['name' => 'Tom Fischer', 'role' => 'Head of Design', 'bio' => 'Obsessed with typography and detail.'], ['name' => 'Lina Haddad', 'role' => 'Engineering Lead', 'bio' => 'Builds fast, accessible products.'], ['name' => 'Kofi Asante', 'role' => 'Client Success', 'bio' => 'Your first call for anything.']]]];

        // ── FAQ ────────────────────────────────────────────────
        $L[] = ['faq-accordion', 'FAQ accordion', 'faq', ['faq', 'accordion', 'questions', 'animated'],
            'Two-column FAQ with smoothly animated open/close.',
            ['type' => 'faq', 'eyebrow' => 'FAQ', 'title' => 'Questions, answered', 'lead' => 'Can not find what you need? We reply within one business day.',
             'primary' => ['label' => 'Contact us', 'href' => '/contact'], 'open_first' => true,
             'items' => [['q' => 'How long does it take to get started?', 'a' => 'Most customers are up and running the same day. We guide you through every step.'], ['q' => 'Can I change plans later?', 'a' => 'Yes — upgrade, downgrade or cancel at any time from your account.'], ['q' => 'Do you offer discounts for non-profits?', 'a' => 'We do. Registered charities and schools receive 30% off every plan.'], ['q' => 'Is my data safe?', 'a' => 'Your data is encrypted in transit and at rest, and backed up daily.']]]];

        // ── Gallery ────────────────────────────────────────────
        $L[] = ['gallery-masonry', 'Masonry gallery', 'gallery', ['gallery', 'masonry', 'photos', 'captions'],
            'Eight-tile masonry grid with captions; swap art for your photos.',
            ['type' => 'gallery', 'eyebrow' => 'Gallery', 'title' => 'Moments we are proud of',
             'items' => [['caption' => 'Opening night'], ['caption' => 'The studio'], ['caption' => 'Workshops'], ['caption' => 'On stage'], ['caption' => 'Summer games'], ['caption' => 'Outdoors'], ['caption' => 'Community day'], ['caption' => 'Celebrations']]]];

        // ── Steps / timeline ───────────────────────────────────
        $L[] = ['steps-process', 'Process steps', 'steps', ['steps', 'process', 'how it works', 'numbers'],
            'Numbered how-it-works steps on a soft background.',
            ['type' => 'timeline', 'variant' => 'steps', 'anchor' => 'how', 'eyebrow' => 'How it works', 'title' => 'Three simple steps',
             'items' => [['title' => 'Tell us your goals', 'text' => 'A short call to understand what success looks like for you.'], ['title' => 'We craft the plan', 'text' => 'A clear proposal with timelines, costs and milestones.'], ['title' => 'Launch and grow', 'text' => 'We deliver, measure and keep improving together.']]]];
        $L[] = ['timeline-history', 'Timeline', 'steps', ['timeline', 'history', 'milestones'],
            'Vertical timeline of milestones.',
            ['type' => 'timeline', 'variant' => 'timeline', 'eyebrow' => 'Our journey', 'title' => 'Milestones along the way',
             'items' => [['meta' => '2012', 'title' => 'Founded', 'text' => 'Started in a tiny studio with two desks and a big idea.'], ['meta' => '2017', 'title' => 'First 100 clients', 'text' => 'Word of mouth carried us further than we imagined.'], ['meta' => '2021', 'title' => 'New headquarters', 'text' => 'A bright, open space for our growing team.'], ['meta' => '2026', 'title' => 'Going global', 'text' => 'Serving customers on five continents.']]]];

        // ── Logos ──────────────────────────────────────────────
        $L[] = ['logos-strip', 'Logo strip', 'logos', ['logos', 'partners', 'marquee', 'trust'],
            'Auto-scrolling partner strip (pauses for reduced motion).',
            ['type' => 'logos', 'title' => 'Trusted by forward-thinking teams',
             'items' => [['name' => 'Northwind'], ['name' => 'Lumen'], ['name' => 'Evergreen'], ['name' => 'Polaris'], ['name' => 'Voltage'], ['name' => 'Harbor'], ['name' => 'Summit'], ['name' => 'Aurora']]]];

        // ── CTA ────────────────────────────────────────────────
        $L[] = ['cta-band', 'CTA band', 'cta', ['cta', 'gradient', 'band', 'conversion'],
            'Full-width gradient call-to-action band.',
            ['type' => 'cta', 'variant' => 'band', 'title' => 'Ready to get started?', 'lead' => 'Join thousands of happy customers today. It only takes a minute.', 'primary' => $go, 'secondary' => ['label' => 'Talk to us', 'href' => '/contact']]];
        $L[] = ['cta-card', 'CTA card', 'cta', ['cta', 'card', 'soft'],
            'Contained call-to-action card with soft art.',
            ['type' => 'cta', 'variant' => 'card', 'title' => 'Let us build something great together', 'lead' => 'Tell us about your project and we will get back to you within a day.', 'primary' => ['label' => 'Start a project', 'href' => '/contact']]];

        // ── Contact ────────────────────────────────────────────
        $L[] = ['contact-map', 'Contact with map', 'contact', ['contact', 'map', 'address', 'phone', 'email'],
            'Contact details beside a stylised map placeholder.',
            ['type' => 'contact', 'eyebrow' => 'Contact', 'title' => 'We would love to hear from you', 'address' => '24 Harbour Street, Lagos',
             'items' => [['icon' => 'phone', 'label' => 'Call us', 'value' => '+234 800 000 0000', 'href' => 'tel:+2348000000000'], ['icon' => 'mail', 'label' => 'Email', 'value' => 'hello@example.com', 'href' => 'mailto:hello@example.com'], ['icon' => 'pin', 'label' => 'Visit', 'value' => '24 Harbour Street, Lagos'], ['icon' => 'clock', 'label' => 'Opening hours', 'value' => 'Mon–Fri, 9:00–17:00']]]];

        // ── News & events ──────────────────────────────────────
        $L[] = ['news-cards', 'News cards', 'news', ['news', 'blog', 'posts', 'cards'],
            'Latest-news cards with tags and illustrated covers.',
            ['type' => 'news', 'variant' => 'cards', 'eyebrow' => 'Latest', 'title' => 'News & stories', 'more' => ['label' => 'All news', 'href' => '/news'],
             'items' => [['tag' => 'Announcement', 'date' => 'Oct 12, 2026', 'title' => 'Introducing our new studio space', 'text' => 'A bright new home designed for collaboration.', 'href' => '#'], ['tag' => 'Guide', 'date' => 'Sep 28, 2026', 'title' => 'Five ways to grow your audience', 'text' => 'Practical ideas you can try this week.', 'href' => '#'], ['tag' => 'Community', 'date' => 'Sep 02, 2026', 'title' => 'Highlights from our summer meetup', 'text' => 'Talks, workshops and plenty of good coffee.', 'href' => '#']]]];
        $L[] = ['events-list', 'Events list', 'news', ['events', 'calendar', 'dates', 'list'],
            'Upcoming events with date tiles.',
            ['type' => 'news', 'variant' => 'events', 'eyebrow' => 'What is on', 'title' => 'Upcoming events', 'more' => ['label' => 'Full calendar', 'href' => '/events'],
             'items' => [['day' => '14', 'month' => 'Nov', 'title' => 'Open house & tours', 'time' => '10:00 – 14:00', 'place' => 'Main hall', 'href' => '#'], ['day' => '22', 'month' => 'Nov', 'title' => 'Design thinking workshop', 'time' => '16:00 – 18:00', 'place' => 'Studio B', 'href' => '#'], ['day' => '06', 'month' => 'Dec', 'title' => 'End-of-year celebration', 'time' => '18:30', 'place' => 'Rooftop', 'href' => '#']]]];

        // ── Newsletter ─────────────────────────────────────────
        $L[] = ['newsletter-card', 'Newsletter signup', 'newsletter', ['newsletter', 'email', 'subscribe', 'form'],
            'Email signup card wired to Mova subscribers (/subscribe).',
            ['type' => 'newsletter', 'title' => 'Stay in the loop', 'lead' => 'One thoughtful email a month. News, ideas and early invites.', 'button' => 'Subscribe']];

        // ── Pricing (ids kept from Phase 2) ────────────────────
        $plans = [
            ['name' => 'Starter', 'text' => 'For individuals getting going.', 'price' => '$9', 'price_b' => '$90', 'per' => '/month', 'per_b' => '/year', 'features' => ['1 website', 'Custom domain', 'Email support'], 'cta' => ['label' => 'Choose Starter', 'href' => '/contact']],
            ['name' => 'Pro', 'text' => 'For growing teams and brands.', 'price' => '$29', 'price_b' => '$290', 'per' => '/month', 'per_b' => '/year', 'featured' => true, 'features' => ['5 websites', 'AI writing assistant', 'Priority support', 'Advanced analytics'], 'cta' => ['label' => 'Choose Pro', 'href' => '/contact']],
            ['name' => 'Business', 'text' => 'For organisations at scale.', 'price' => '$79', 'price_b' => '$790', 'per' => '/month', 'per_b' => '/year', 'features' => ['Unlimited websites', 'SSO & roles', 'Dedicated manager', 'SLA'], 'cta' => ['label' => 'Talk to sales', 'href' => '/contact']],
        ];
        $L[] = ['pricing-saas-featured', 'Pricing — featured plan', 'pricing', ['pricing', 'plans', 'featured', 'saas'],
            'Three plans with a highlighted, elevated middle tier.',
            ['type' => 'pricing', 'variant' => 'cards', 'eyebrow' => 'Pricing', 'title' => 'Simple, transparent pricing', 'lead' => 'Start free. Upgrade when you are ready.', 'plans' => $plans, 'note' => 'All prices exclude VAT. Cancel any time.']];
        $L[] = ['pricing-compact-stack', 'Pricing — compact', 'pricing', ['pricing', 'compact', 'list', 'membership'],
            'Compact stacked plans for memberships and fees.',
            ['type' => 'pricing', 'variant' => 'compact', 'eyebrow' => 'Membership', 'title' => 'Choose your membership', 'plans' => $plans]];
        $L[] = ['pricing-horizontal-toggle', 'Pricing — billing toggle', 'pricing', ['pricing', 'toggle', 'monthly', 'yearly'],
            'Monthly / yearly toggle with animated price swap.',
            ['type' => 'pricing', 'variant' => 'toggle', 'eyebrow' => 'Plans', 'title' => 'Pay monthly or save yearly', 'save' => 'Save 17%', 'plans' => $plans]];

        $out = [];
        foreach ($L as $e) {
            $out[] = ['id' => $e[0], 'name' => $e[1], 'category' => $e[2], 'tags' => $e[3], 'description' => $e[4], 'spec' => $e[5]];
        }
        return $out;
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $e) {
            if ($e['id'] === $id) {
                return $e;
            }
        }
        return null;
    }
}
