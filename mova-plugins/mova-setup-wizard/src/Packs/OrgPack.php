<?php

declare(strict_types=1);

namespace MovaSetupWizard\Packs;

final class OrgPack
{
    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'id' => 'organization',
            'label' => 'Organization',
            'pages' => [
                [
                    'slug' => 'home',
                    'title' => 'Home',
                    'role' => 'front',
                    'body' => <<<'HTML'
<section class="wizard-section">
  <h1>{{site_name}}</h1>
  <p class="lead">{{tagline}}</p>
  <p>Welcome. Use this homepage to introduce your mission, programmes, and ways to get involved.</p>
  <ul>
    <li><a href="/about">About us</a></li>
    <li><a href="/programs">Programs</a></li>
    <li><a href="/team">Team</a></li>
    <li><a href="/contact">Contact</a></li>
  </ul>
</section>
HTML,
                ],
                [
                    'slug' => 'about',
                    'title' => 'About us',
                    'role' => 'about',
                    'body' => <<<'HTML'
<section class="wizard-section">
  <h1>About {{site_name}}</h1>
  <p>{{about_seed}}</p>
  <p>Describe your mission, history, and impact. Edit this page anytime in HQ.</p>
</section>
HTML,
                ],
                [
                    'slug' => 'programs',
                    'title' => 'Programs',
                    'role' => 'programs',
                    'body' => <<<'HTML'
<section class="wizard-section">
  <h1>Programs</h1>
  <p>List the programmes, services, or initiatives you offer. Add details, schedules, and how people can join.</p>
</section>
HTML,
                ],
                [
                    'slug' => 'team',
                    'title' => 'Team',
                    'role' => 'team',
                    'body' => <<<'HTML'
<section class="wizard-section">
  <h1>Our team</h1>
  <p>Introduce leadership and key people. Add photos and short bios when ready.</p>
</section>
HTML,
                ],
                [
                    'slug' => 'contact',
                    'title' => 'Contact',
                    'role' => 'contact',
                    'body' => <<<'HTML'
<section class="wizard-section">
  <h1>Contact</h1>
  <p>Reach out — we are happy to help.</p>
  <p>{{contact_seed}}</p>
</section>
HTML,
                ],
            ],
            'nav' => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'About us', 'url' => '/about'],
                ['label' => 'Programs', 'url' => '/programs'],
                ['label' => 'Team', 'url' => '/team'],
                ['label' => 'Contact', 'url' => '/contact'],
            ],
            'footer_columns' => [
                [
                    'title' => 'Explore',
                    'links' => [
                        ['label' => 'About us', 'url' => '/about'],
                        ['label' => 'Programs', 'url' => '/programs'],
                        ['label' => 'Team', 'url' => '/team'],
                    ],
                ],
                [
                    'title' => 'Contact',
                    'text' => '{{contact_seed}}',
                    'links' => [
                        ['label' => 'Contact page', 'url' => '/contact'],
                    ],
                ],
                [
                    'title' => 'Connect',
                    'links' => [
                        ['label' => 'Home', 'url' => '/'],
                    ],
                ],
            ],
        ];
    }
}
