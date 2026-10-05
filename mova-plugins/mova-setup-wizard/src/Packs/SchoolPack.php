<?php

declare(strict_types=1);

namespace MovaSetupWizard\Packs;

final class SchoolPack
{
    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'id' => 'school',
            'label' => 'School',
            'pages' => [
                [
                    'slug' => 'home',
                    'title' => 'Home',
                    'role' => 'front',
                    'body' => <<<'HTML'
<section class="wizard-section">
  <h1>Welcome to {{site_name}}</h1>
  <p class="lead">{{tagline}}</p>
  <p>Explore academics, admissions, and life at our school. Edit this homepage in HQ to add news, highlights, and calls to action.</p>
  <ul>
    <li><a href="/about">About us</a></li>
    <li><a href="/academics">Academics</a></li>
    <li><a href="/admissions">Admissions</a></li>
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
  <p>Share your mission, history, values, and what makes this school a great place to learn and grow.</p>
</section>
HTML,
                ],
                [
                    'slug' => 'academics',
                    'title' => 'Academics',
                    'role' => 'generic',
                    'body' => <<<'HTML'
<section class="wizard-section">
  <h1>Academics</h1>
  <p>Outline programmes, curriculum highlights, and learning pathways at {{site_name}}.</p>
  <p>Replace this placeholder with departments, grade levels, or featured courses.</p>
</section>
HTML,
                ],
                [
                    'slug' => 'admissions',
                    'title' => 'Admissions',
                    'role' => 'generic',
                    'body' => <<<'HTML'
<section class="wizard-section">
  <h1>Admissions</h1>
  <p>Explain how families apply, key dates, requirements, and next steps.</p>
  <p>Add links to forms, fees, and open-day information when ready.</p>
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
  <p>We would love to hear from you.</p>
  <p>{{contact_seed}}</p>
</section>
HTML,
                ],
            ],
            'nav' => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'About us', 'url' => '/about'],
                ['label' => 'Academics', 'url' => '/academics'],
                ['label' => 'Admissions', 'url' => '/admissions'],
                ['label' => 'Contact', 'url' => '/contact'],
            ],
            'footer_columns' => [
                [
                    'title' => 'Explore',
                    'links' => [
                        ['label' => 'About us', 'url' => '/about'],
                        ['label' => 'Academics', 'url' => '/academics'],
                        ['label' => 'Admissions', 'url' => '/admissions'],
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
