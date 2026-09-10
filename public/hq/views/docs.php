<?php
/**
 * Mova HQ — built-in documentation
 */
?>
<div class="mova-docs">
    <div class="mova-docs-hero">
        <div>
            <span class="mova-docs-eyebrow">MOVA CMS · DOCUMENTATION</span>
            <h1>Get Mova up and running.</h1>
            <p class="mova-docs-lead">A practical guide to your first site, followed by a deeper look at how Mova's content, themes, extensions, APIs, security and operations fit together.</p>
        </div>
        <div class="mova-docs-version">v1.0.0</div>
    </div>

    <div class="mova-docs-grid">
        <aside class="mova-docs-toc">
            <div class="mova-docs-toc-title">On this page</div>
            <a href="#quick-start">Quick start</a>
            <a href="#first-content">Create your first content</a>
            <a href="#design">Shape the site</a>
            <a href="#publishing">Publishing workflow</a>
            <a href="#deep-dive">Deep dive</a>
            <a href="#architecture">Architecture</a>
            <a href="#content-model">Content model</a>
            <a href="#themes">Themes & design</a>
            <a href="#extensions">Plugins, API & webhooks</a>
            <a href="#operations">Security, backups & health</a>
            <a href="#deployment">Deployment checklist</a>
        </aside>

        <article class="mova-docs-article">
            <section id="quick-start" class="mova-docs-section">
                <span class="mova-docs-kicker">01 · QUICK START</span>
                <h2>Get the app online in minutes</h2>
                <p>Mova is designed to run on ordinary PHP shared hosting. A fresh install uses SQLite, so you do not need to create a separate MySQL database.</p>

                <div class="mova-docs-steps">
                    <div class="mova-docs-step">
                        <b>1</b>
                        <div><h3>Upload the package</h3><p>Extract everything inside <code>mova-shared-hosting/</code> into the document root, normally the subdomain's <code>public_html</code>. The root should contain <code>index.php</code>, <code>app/</code>, <code>hq/</code>, <code>assets/</code> and <code>config/</code>.</p></div>
                    </div>
                    <div class="mova-docs-step">
                        <b>2</b>
                        <div><h3>Check the host</h3><p>Use PHP 8.1+ with SQLite/PDO SQLite, GD and Apache <code>mod_rewrite</code>. Make <code>storage/</code> and <code>mova-uploads/</code> writable (usually 755 or 775).</p></div>
                    </div>
                    <div class="mova-docs-step">
                        <b>3</b>
                        <div><h3>Run the installer</h3><p>Open <code>/hq/install</code>. Mova creates its SQLite schema and then asks you to create the owner account. After installation, sign in at <code>/hq</code>.</p></div>
                    </div>
                    <div class="mova-docs-step">
                        <b>4</b>
                        <div><h3>Configure the basics</h3><p>Open Settings and set the site name, description and other global options. Then use Design to establish identity, colors, typography, layout and components.</p></div>
                    </div>
                </div>

                <div class="mova-docs-callout">
                    <strong>First-run rule:</strong> if you see a hosting provider's welcome page instead of Mova, open <code>/hq/install</code>. The installer can neutralize common placeholder <code>index.html</code> files.
                </div>
            </section>

            <section id="first-content" class="mova-docs-section">
                <span class="mova-docs-kicker">02 · FIRST CONTENT</span>
                <h2>Create something visitors can see</h2>
                <ol class="mova-docs-list">
                    <li>Go to <strong>Content → New content</strong>.</li>
                    <li>Choose the appropriate content type and enter a title, body and optional metadata.</li>
                    <li>Add categories, tags and a featured image where useful.</li>
                    <li>Review the SEO settings before publishing.</li>
                    <li>Publish immediately or use scheduling when you want Mova to publish it later.</li>
                    <li>Use <strong>View site</strong> in the top bar to preview the public site.</li>
                </ol>
                <p>Published content is available through its slug. The homepage can use a selected piece of fixed content, or fall back to a feed of recent published items.</p>
            </section>

            <section id="design" class="mova-docs-section">
                <span class="mova-docs-kicker">03 · DESIGN</span>
                <h2>Make the site yours</h2>
                <p>The Design workspace separates the visual system into manageable pieces instead of forcing you to edit templates for every small change.</p>
                <div class="mova-docs-cards">
                    <div><h3>Brand</h3><p>Identity, logo/favicon and theme package settings.</p></div>
                    <div><h3>Style</h3><p>Light/dark colors, typography, radius, shadows and density.</p></div>
                    <div><h3>Layout</h3><p>Container, header, navigation, mobile menu and footer.</p></div>
                    <div><h3>Components</h3><p>Reusable button, card and hero presentation choices.</p></div>
                    <div><h3>Elements</h3><p>Editor controls and applied design settings.</p></div>
                </div>
            </section>

            <section id="publishing" class="mova-docs-section">
                <span class="mova-docs-kicker">04 · PUBLISHING</span>
                <h2>A simple content lifecycle</h2>
                <div class="mova-docs-flow">
                    <span>Draft</span><i>→</i><span>Review</span><i>→</i><span>Approved</span><i>→</i><span>Scheduled</span><i>→</i><span>Published</span>
                </div>
                <p>The editor supports the broader workflow states shown in Content, including archived and trash states. Scheduled content is checked on public requests and due items are published automatically; the page cache is flushed when that happens.</p>
            </section>

            <section id="deep-dive" class="mova-docs-section mova-docs-deep">
                <span class="mova-docs-kicker">05 · DEEP DIVE</span>
                <h2>How Mova works under the hood</h2>
                <p>This section is for developers and maintainers who want to understand the install package before extending it.</p>
            </section>

            <section id="architecture" class="mova-docs-section">
                <h2>Architecture</h2>
                <p>Mova uses a lightweight front-controller architecture. Public requests enter <code>index.php</code>; administration requests enter <code>hq/index.php</code>. Both bootstrap the core and dispatch requests through the router.</p>
                <div class="mova-docs-code">
                    <div><span>public request</span><b>→</b><span>index.php</span><b>→</b><span>Bootstrap</span><b>→</b><span>Router</span><b>→</b><span>Theme / response</span></div>
                    <div><span>admin request</span><b>→</b><span>hq/index.php</span><b>→</b><span>Bootstrap</span><b>→</b><span>HQ routes</span><b>→</b><span>HQ view</span></div>
                </div>
                <p>Core services are organized by responsibility: authentication, content, media, SEO, caching, mail, AI, integrations, plugins, backups, security and health. Route files under <code>hq/routes/</code> keep the admin surface modular.</p>
            </section>

            <section id="content-model" class="mova-docs-section">
                <h2>Content, taxonomy and media</h2>
                <p>The content repository is the main gateway for reading and writing content. Content types and fields let the installation describe different kinds of entries, while categories and tags provide taxonomy.</p>
                <ul class="mova-docs-list">
                    <li><strong>Content:</strong> titles, slugs, body, status, scheduling and relationships.</li>
                    <li><strong>Media:</strong> uploaded assets stored under <code>mova-uploads/</code>, with image helpers for responsive output.</li>
                    <li><strong>Taxonomy:</strong> categories and tags for organization and discovery.</li>
                    <li><strong>Relations:</strong> connect related content without hard-coding links into templates.</li>
                    <li><strong>Search:</strong> public <code>/search?q=...</code> searches content through the repository.</li>
                </ul>
            </section>

            <section id="themes" class="mova-docs-section">
                <h2>Themes and rendering</h2>
                <p>The active theme lives under <code>mova-themes/</code>. Mova renders a theme layout around view templates such as the homepage, content page, search page and 404 page. If the selected theme is missing a required view, the default theme is used as a fallback.</p>
                <p>The install includes several theme packages, including a documentation-oriented theme. Theme metadata is kept in each theme's <code>theme.json</code>, making themes portable and easier to identify.</p>
                <div class="mova-docs-callout">
                    <strong>Developer tip:</strong> keep presentation in the theme and business logic in services/repositories. That separation makes a visual redesign much safer.
                </div>
            </section>

            <section id="extensions" class="mova-docs-section">
                <h2>Plugins, integrations, API and webhooks</h2>
                <p>Mova has an extension layer for functionality that should not be baked into the core. The Extend workspace exposes plugins and integrations, while API keys and webhooks provide controlled connections to external systems.</p>
                <div class="mova-docs-cards">
                    <div><h3>Plugins</h3><p>Package optional functionality in <code>mova-plugins/</code>. The install includes example/plugin packages to demonstrate the structure.</p></div>
                    <div><h3>API keys</h3><p>Create credentials for programmatic access. Treat keys like passwords and rotate or revoke them when exposure is suspected.</p></div>
                    <div><h3>Webhooks</h3><p>Expose event-driven connections to external services without coupling them to a theme.</p></div>
                    <div><h3>Integrations</h3><p>Configure supported external services from the admin layer rather than embedding credentials in templates.</p></div>
                </div>
            </section>

            <section id="operations" class="mova-docs-section">
                <h2>Security, backups, health and performance</h2>
                <ul class="mova-docs-list">
                    <li><strong>Authentication:</strong> HQ pages require an authenticated user; the installer creates an owner account.</li>
                    <li><strong>CSRF:</strong> installation and state-changing admin operations use CSRF protection where applicable.</li>
                    <li><strong>Rate limiting:</strong> API services include rate-limiting support.</li>
                    <li><strong>Backups:</strong> the backup service can ensure a weekly backup and the Operations area exposes backup controls.</li>
                    <li><strong>Page cache:</strong> anonymous GET pages are cached, while HQ and search requests bypass the public page cache.</li>
                    <li><strong>SEO:</strong> Mova exposes <code>/robots.txt</code>, <code>/sitemap.xml</code>, <code>/feed.xml</code> and <code>/llms.txt</code>.</li>
                    <li><strong>Privacy-aware analytics:</strong> page views store a daily salted hash of the visitor IP rather than the raw address.</li>
                </ul>
            </section>

            <section id="deployment" class="mova-docs-section">
                <h2>Production deployment checklist</h2>
                <div class="mova-docs-checklist">
                    <label><input type="checkbox"> PHP 8.1+ is enabled</label>
                    <label><input type="checkbox"> PDO SQLite and GD are enabled</label>
                    <label><input type="checkbox"> Apache rewrite rules are working</label>
                    <label><input type="checkbox"> <code>storage/</code> is writable</label>
                    <label><input type="checkbox"> <code>mova-uploads/</code> is writable</label>
                    <label><input type="checkbox"> HTTPS is enabled</label>
                    <label><input type="checkbox"> Owner credentials are strong and unique</label>
                    <label><input type="checkbox"> A backup strategy is configured</label>
                    <label><input type="checkbox"> Site, SEO and email settings have been tested</label>
                    <label><input type="checkbox"> Public pages, search, sitemap and feed have been checked</label>
                </div>
            </section>

            <section class="mova-docs-section mova-docs-next">
                <h2>Next step</h2>
                <p>Start with the Quick start above. Once the site is live, use the Deep dive sections as your developer reference while customizing themes, content types, plugins and integrations.</p>
                <div class="mova-docs-actions">
                    <a class="btn-primary" href="/hq">Back to HQ</a>
                    <a class="btn" href="/hq/settings">Open settings</a>
                </div>
            </section>
        </article>
    </div>
</div>
