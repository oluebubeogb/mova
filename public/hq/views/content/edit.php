<?php
use Mova\Security\Csrf;
$isNew = $content === null;
$action = $isNew ? '/hq/content/new' : '/hq/content/edit/' . (int) $content['id'];
$meta = $content['meta'] ?? [];
$categories = $categories ?? [];
$selectedCategories = $selectedCategories ?? [];
$tagString = $tagString ?? '';
$publishedAtLocal = '';
if (!empty($content['published_at'])) {
    $publishedAtLocal = date('Y-m-d\TH:i', strtotime($content['published_at']));
}
?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Content saved.</div>
<?php endif; ?>
<?php if (!empty($_GET['dev_error'])): ?>
    <div class="alert alert-error" style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;">
        <strong>Dev Editor:</strong> <?= htmlspecialchars(urldecode((string)$_GET['dev_error'])) ?>
        <br><span style="font-size:0.85em;">Page was saved as draft because of validation errors.</span>
    </div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="content-form" id="content-form">
    <?= Csrf::field() ?>

    <div class="form-layout sidebar-closed" id="content-form-layout">
        <div class="form-main">
            <div class="form-group">
                <input type="text" name="title" class="input-title" placeholder="Title" required
                       value="<?= htmlspecialchars($content['title'] ?? '') ?>" autofocus>
            </div>
            <div class="form-group">
                <label>Body</label>
                <div class="editor-toolbar-wrap" id="editor-toolbar-wrap">
                    <!-- LINE 1: primary actions + groups (desktop) / quick actions (mobile) -->
                    <div class="editor-toolbar editor-toolbar-line1" id="editor-toolbar-line1" role="toolbar" aria-label="Primary formatting">
                        <div class="toolbar-group toolbar-history">
                            <button type="button" data-cmd="undo" title="Undo" aria-label="Undo"><i class="fa-solid fa-rotate-left"></i></button>
                            <button type="button" data-cmd="redo" title="Redo" aria-label="Redo"><i class="fa-solid fa-rotate-right"></i></button>
                        </div>
                        <span class="toolbar-sep"></span>

                        <!-- Desktop group tabs -->
                        <div class="toolbar-group toolbar-groups-desktop" role="tablist" aria-label="Tool groups">
                            <button type="button" class="toolbar-tab is-active" data-group="text" role="tab" aria-selected="true" title="Text">Text</button>
                            <button type="button" class="toolbar-tab" data-group="paragraph" role="tab" aria-selected="false" title="Paragraph">Paragraph</button>
                            <button type="button" class="toolbar-tab" data-group="align" role="tab" aria-selected="false" title="Align">Align</button>
                            <button type="button" class="toolbar-tab" data-group="insert" role="tab" aria-selected="false" title="Insert">Insert</button>
                            <button type="button" class="toolbar-tab" data-group="media" role="tab" aria-selected="false" title="Media">Media</button>
                            <button type="button" class="toolbar-tab" data-group="advanced" role="tab" aria-selected="false" title="Advanced">Advanced</button>
                        </div>

                        <!-- Mobile quick actions -->
                        <div class="toolbar-group toolbar-mobile-quick">
                            <button type="button" data-cmd="bold" title="Bold" aria-label="Bold"><b>B</b></button>
                            <button type="button" data-cmd="italic" title="Italic" aria-label="Italic"><i style="font-style:italic;font-weight:600;">I</i></button>
                            <button type="button" data-cmd="underline" title="Underline" aria-label="Underline"><span style="text-decoration:underline;font-weight:600;">U</span></button>
                            <button type="button" id="btn-link" title="Link" aria-label="Link"><i class="fa-solid fa-link"></i></button>
                            <button type="button" id="btn-image" title="Image" aria-label="Image"><i class="fa-solid fa-image"></i></button>
                            <button type="button" id="btn-toolbar-more" class="toolbar-more-btn" title="More tools" aria-label="More tools" aria-expanded="false"><i class="fa-solid fa-ellipsis"></i></button>
                        </div>
                    </div>

                    <!-- LINE 2: dynamic child tools -->
                    <div class="editor-toolbar editor-toolbar-line2" id="editor-toolbar-line2" role="toolbar" aria-label="Group tools" data-mode="group" data-active-group="text">
                        <!-- Desktop / mobile child panels -->
                        <div class="toolbar-panel is-active" data-panel="text">
                            <button type="button" class="toolbar-back" data-toolbar-back title="Back" aria-label="Back"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"><i class="fa-solid fa-bold"></i></button>
                            <button type="button" data-cmd="italic" title="Italic (Ctrl+I)"><i class="fa-solid fa-italic"></i></button>
                            <button type="button" data-cmd="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
                            <button type="button" data-cmd="strikeThrough" title="Strikethrough"><i class="fa-solid fa-strikethrough"></i></button>
                            <span class="toolbar-sep"></span>
                            <button type="button" data-cmd="formatBlock" data-value="h2" title="Heading 2"><i class="fa-solid fa-heading"></i> 2</button>
                            <button type="button" data-cmd="formatBlock" data-value="h3" title="Heading 3"><i class="fa-solid fa-heading"></i> 3</button>
                            <button type="button" data-cmd="formatBlock" data-value="h4" title="Heading 4"><i class="fa-solid fa-heading"></i> 4</button>
                            <button type="button" data-cmd="formatBlock" data-value="blockquote" title="Quote"><i class="fa-solid fa-quote-left"></i></button>
                            <button type="button" id="btn-code" title="Inline code"><i class="fa-solid fa-terminal"></i></button>
                            <button type="button" id="btn-clear" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>
                        </div>

                        <div class="toolbar-panel" data-panel="paragraph">
                            <button type="button" class="toolbar-back" data-toolbar-back title="Back" aria-label="Back"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" data-cmd="formatBlock" data-value="p" title="Normal text"><i class="fa-solid fa-paragraph"></i></button>
                            <button type="button" data-cmd="formatBlock" data-value="h2" title="Heading 2"><i class="fa-solid fa-heading"></i> 2</button>
                            <button type="button" data-cmd="formatBlock" data-value="h3" title="Heading 3"><i class="fa-solid fa-heading"></i> 3</button>
                            <button type="button" data-cmd="formatBlock" data-value="h4" title="Heading 4"><i class="fa-solid fa-heading"></i> 4</button>
                            <span class="toolbar-sep"></span>
                            <button type="button" data-cmd="insertUnorderedList" title="Bullet list"><i class="fa-solid fa-list-ul"></i></button>
                            <button type="button" data-cmd="insertOrderedList" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
                            <button type="button" data-cmd="outdent" title="Decrease indent"><i class="fa-solid fa-outdent"></i></button>
                            <button type="button" data-cmd="indent" title="Increase indent"><i class="fa-solid fa-indent"></i></button>
                            <button type="button" data-cmd="insertHorizontalRule" title="Horizontal rule"><i class="fa-solid fa-minus"></i></button>
                        </div>

                        <div class="toolbar-panel" data-panel="align">
                            <button type="button" class="toolbar-back" data-toolbar-back title="Back" aria-label="Back"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" data-cmd="justifyLeft" title="Align left"><i class="fa-solid fa-align-left"></i></button>
                            <button type="button" data-cmd="justifyCenter" title="Align center"><i class="fa-solid fa-align-center"></i></button>
                            <button type="button" data-cmd="justifyRight" title="Align right"><i class="fa-solid fa-align-right"></i></button>
                            <button type="button" data-cmd="justifyFull" title="Justify"><i class="fa-solid fa-align-justify"></i></button>
                        </div>

                        <div class="toolbar-panel" data-panel="insert">
                            <button type="button" class="toolbar-back" data-toolbar-back title="Back" aria-label="Back"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" id="btn-link-desk" class="btn-link-alias" title="Link"><i class="fa-solid fa-link"></i></button>
                            <button type="button" id="btn-unlink" title="Remove link"><i class="fa-solid fa-link-slash"></i></button>
                            <button type="button" id="btn-table" title="Table"><i class="fa-solid fa-table"></i></button>
                            <button type="button" data-cmd="insertHorizontalRule" title="Divider"><i class="fa-solid fa-minus"></i></button>
                            <button type="button" id="btn-pre" title="Code block"><i class="fa-solid fa-code"></i></button>
                            <button type="button" id="btn-embed" title="Embed (iframe / HTML)"><i class="fa-solid fa-window-maximize"></i></button>
                            <button type="button" id="btn-assembly" title="Insert Assembly"><i class="fa-solid fa-cubes"></i></button>
                        </div>

                        <div class="toolbar-panel" data-panel="media">
                            <button type="button" class="toolbar-back" data-toolbar-back title="Back" aria-label="Back"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" id="btn-image-desk" class="btn-image-alias" title="Image"><i class="fa-solid fa-image"></i></button>
                            <button type="button" id="btn-gallery" title="Gallery"><i class="fa-solid fa-images"></i></button>
                            <button type="button" id="btn-video" title="Video embed"><i class="fa-solid fa-video"></i></button>
                            <button type="button" id="btn-embed-media" class="btn-embed-alias" title="Media embed"><i class="fa-solid fa-window-maximize"></i></button>
                        </div>

                        <div class="toolbar-panel" data-panel="advanced">
                            <button type="button" class="toolbar-back" data-toolbar-back title="Back" aria-label="Back"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" id="btn-pre-adv" class="btn-pre-alias" title="Code block"><i class="fa-solid fa-code"></i></button>
                            <button type="button" id="btn-clear-adv" class="btn-clear-alias" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>
                            <button type="button" id="btn-callout" title="Callout / info block"><i class="fa-solid fa-circle-info"></i></button>
                            <button type="button" id="btn-button" title="Button"><i class="fa-solid fa-square"></i></button>
                            <button type="button" id="btn-faq" title="FAQ item"><i class="fa-solid fa-circle-question"></i></button>
                            <button type="button" id="btn-embed-adv" class="btn-embed-alias" title="HTML embed"><i class="fa-solid fa-window-maximize"></i></button>
                        </div>

                        <!-- Mobile group list (shown when More is open, before drilling into a group) -->
                        <div class="toolbar-panel toolbar-panel-groups" data-panel="groups">
                            <button type="button" class="toolbar-group-pick" data-group="text">Text</button>
                            <button type="button" class="toolbar-group-pick" data-group="paragraph">Paragraph</button>
                            <button type="button" class="toolbar-group-pick" data-group="align">Align</button>
                            <button type="button" class="toolbar-group-pick" data-group="insert">Insert</button>
                            <button type="button" class="toolbar-group-pick" data-group="media">Media</button>
                            <button type="button" class="toolbar-group-pick" data-group="advanced">Advanced</button>
                        </div>
                    </div>
                </div>
                <div class="editor-body" id="editor" contenteditable="true" data-placeholder="Start writing…"><?php
                    $hqBody = $content['body'] ?? '';
                    // Prevent Dev/HTML styles from restyling HQ (style tags in contenteditable apply globally)
                    $hqBody = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $hqBody) ?? $hqBody;
                    $hqBody = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $hqBody) ?? $hqBody;
                    echo $hqBody;
                ?></div>
                <textarea name="body" id="body-input" hidden><?= htmlspecialchars($content['body'] ?? '') ?></textarea>
            </div>

            <?php
            // Plugin hook: content.edit.render (Dev Editor panels, etc.)
            if (class_exists(\Mova\Plugin\PluginManager::class)) {
                \Mova\Plugin\PluginManager::doAction('content.edit.render', [
                    'content' => $content ?? [],
                    'isNew' => $isNew ?? false,
                ]);
            }
            ?>

            <div class="form-group">
                <label>Excerpt</label>
                <textarea name="excerpt" rows="6" placeholder="Short summary for listings and SEO"><?= htmlspecialchars($content['excerpt'] ?? '') ?></textarea>
            </div>


            <script>
            (function () {
              var editor = document.getElementById('editor');
              if (editor) {
                editor.querySelectorAll('style, script, link[rel="stylesheet"]').forEach(function (el) {
                  el.remove();
                });
              }
            })();
            </script>

            <div class="panel" id="ai-assist-panel" style="margin-top:1rem;">
                <h3 style="margin-top:0;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
                    <span><i class="fa-solid fa-wand-magic-sparkles"></i> AI assist</span>
                    <button type="button" class="btn-ghost" id="btn-analyze" style="font-size:0.8rem;">Run SEO analysis</button>
                </h3>
                <p style="font-size:0.8rem;color:var(--hq-muted);margin:0 0 0.75rem;">Suggestions only — nothing is published automatically.</p>
                <div class="ai-actions" style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.75rem;">
                    <button type="button" class="btn-ghost ai-btn" data-ai="title">Titles</button>
                    <button type="button" class="btn-ghost ai-btn" data-ai="excerpt">Excerpt</button>
                    <button type="button" class="btn-ghost ai-btn" data-ai="meta">Meta desc</button>
                    <button type="button" class="btn-ghost ai-btn" data-ai="outline">Outline</button>
                    <button type="button" class="btn-ghost ai-btn" data-ai="keywords">Keywords</button>
                    <button type="button" class="btn-ghost ai-btn" data-ai="faq">FAQ</button>
                    <button type="button" class="btn-ghost ai-btn" data-ai="summarize">Summarize</button>
                    <button type="button" class="btn-ghost ai-btn" data-ai="improve">Improve</button>
                    <button type="button" class="btn-ghost" id="btn-link-suggest">Internal links</button>
                </div>
                <div id="ai-output" style="display:none;background:var(--hq-input-bg);border:1px solid var(--hq-border);border-radius:8px;padding:0.85rem;font-size:0.875rem;white-space:pre-wrap;max-height:240px;overflow:auto;"></div>
                <div id="analysis-output" style="margin-top:0.75rem;"></div>
            </div>
        </div>

        <aside class="form-sidebar" id="content-form-sidebar" hidden>
            <div class="form-sidebar-toolbar">
                <span class="form-sidebar-title" id="form-sidebar-title">Tool</span>
                <button type="button" class="form-sidebar-close" id="btn-close-sidebar" title="Close sidebar" aria-label="Close sidebar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="panel collapsible" data-panel="publish">
                <button type="button" class="panel-head" aria-expanded="false">
                    <h3>Publish</h3>
                    <i class="fa-solid fa-chevron-down panel-chevron" aria-hidden="true"></i>
                </button>
                <div class="panel-body">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="status-select">
                        <?php
                        $statuses = [
                            'draft' => 'Draft',
                            'review' => 'In review',
                            'approved' => 'Approved',
                            'published' => 'Published',
                            'scheduled' => 'Scheduled',
                            'archived' => 'Archived',
                        ];
                        $current = $content['status'] ?? 'draft';
                        foreach ($statuses as $k => $label):
                        ?>
                            <option value="<?= $k ?>" <?= $current === $k ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <div class="slug-field">
                        <span class="slug-prefix">/</span>
                        <input type="text" name="slug" value="<?= htmlspecialchars($content['slug'] ?? '') ?>" placeholder="auto-generated-from-title">
                    </div>
                </div>
                <div class="form-group" id="publish-at-group">
                    <label>Publish at</label>
                    <input type="datetime-local" name="published_at" value="<?= htmlspecialchars($publishedAtLocal) ?>">
                    <p style="font-size:0.75rem;color:var(--hq-muted);margin:0.35rem 0 0;">Required for scheduled. Also sets the public date when published.</p>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type">
                        <?php
                        $currentType = $content['type'] ?? 'article';
                        foreach ($types as $k => $label):
                        ?>
                            <option value="<?= $k ?>" <?= $currentType === $k ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label" style="display:flex;align-items:flex-start;gap:0.5rem;font-weight:400;">
                        <input type="checkbox" name="hide_article_chrome" value="1" id="hide-article-chrome"
                            <?= !empty($meta['hide_article_chrome']) && $meta['hide_article_chrome'] !== '0' ? 'checked' : '' ?>>
                        <span>
                            Hide title, dates &amp; type on frontend
                            <span style="display:block;font-size:0.75rem;color:var(--hq-muted);margin-top:0.2rem;">When checked, the article header (title, publish/update dates, content type) is hidden from public visitors.</span>
                        </span>
                    </label>
                </div>
                <button type="submit" class="btn-primary btn-block" id="btn-publish-save">Save</button>
                <?php if (!$isNew && ($content['status'] ?? '') === 'published'): ?>
                    <a href="/<?= htmlspecialchars($content['slug']) ?>" target="_blank" class="btn-ghost btn-block" style="margin-top:0.5rem;text-align:center;display:block;">View →</a>
                <?php endif; ?>
                <?php if (!$isNew): ?>
                    <button type="submit" form="trash-form" class="btn-danger btn-block" style="margin-top:0.5rem;" onclick="return confirm('Move to trash?');">Trash</button>
                <?php endif; ?>
            </div>
            </div>

            <div class="panel collapsible" data-panel="categories">
                <button type="button" class="panel-head" aria-expanded="false">
                    <h3>Categories</h3>
                    <i class="fa-solid fa-chevron-down panel-chevron" aria-hidden="true"></i>
                </button>
                <div class="panel-body">
                <?php if (empty($categories)): ?>
                    <p style="font-size:0.85rem;color:var(--hq-muted);margin:0;">No categories. <a href="/hq/categories">Create one</a></p>
                <?php else: ?>
                    <div class="form-group" style="max-height:160px;overflow:auto;">
                        <?php foreach ($categories as $cat): ?>
                            <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;font-weight:400;color:var(--hq-text);">
                                <input type="checkbox" name="categories[]" value="<?= (int) $cat['id'] ?>"
                                    <?= in_array((int) $cat['id'], $selectedCategories, true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            </div>

            <div class="panel collapsible" data-panel="tags">
                <button type="button" class="panel-head" aria-expanded="false">
                    <h3>Tags</h3>
                    <i class="fa-solid fa-chevron-down panel-chevron" aria-hidden="true"></i>
                </button>
                <div class="panel-body">
                <div class="form-group">
                    <input type="text" name="tags" value="<?= htmlspecialchars($tagString) ?>" placeholder="php, seo, cms">
                    <p style="font-size:0.75rem;color:var(--hq-muted);margin:0.35rem 0 0;">Comma-separated. New tags are created automatically.</p>
                </div>
            </div>
            </div>

            <div class="panel collapsible" data-panel="featured-image">
                <button type="button" class="panel-head" aria-expanded="false">
                    <h3>Featured image</h3>
                    <i class="fa-solid fa-chevron-down panel-chevron" aria-hidden="true"></i>
                </button>
                <div class="panel-body">
                <div class="form-group">
                    <input type="text" name="featured_image" id="featured-image" placeholder="/mova-uploads/..." value="<?= htmlspecialchars($content['featured_image'] ?? '') ?>">
                    <div id="featured-image-preview" style="margin-top:0.5rem;<?php if (empty($content['featured_image'])): ?>display:none;<?php endif ?>">
                        <?php if (!empty($content['featured_image'])): ?>
                        <img src="<?= htmlspecialchars($content['featured_image']) ?>" alt="" style="max-width:100%;max-height:140px;border-radius:8px;border:1px solid var(--hq-border);object-fit:cover;">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            </div>

            <?php
            $customFields = $customFields ?? [];
            $fieldValues = $fieldValues ?? [];
            $relatedIds = $relatedIds ?? [];
            $relatedOptions = $relatedOptions ?? [];
            $revisions = $revisions ?? [];
            ?>

            <?php if (!empty($customFields)): ?>
            <div class="panel collapsible" data-panel="custom-fields">
                <button type="button" class="panel-head" aria-expanded="false">
                    <h3>Custom fields</h3>
                    <i class="fa-solid fa-chevron-down panel-chevron" aria-hidden="true"></i>
                </button>
                <div class="panel-body">
                <?php foreach ($customFields as $field): ?>
                    <?php
                    $fname = 'fields[' . htmlspecialchars($field['slug']) . ']';
                    $fval = $fieldValues[$field['slug']] ?? '';
                    $ftype = $field['field_type'];
                    ?>
                    <div class="form-group">
                        <label><?= htmlspecialchars($field['name']) ?><?= (int) $field['is_required'] ? ' *' : '' ?></label>
                        <?php if ($ftype === 'textarea' || $ftype === 'richtext'): ?>
                            <textarea name="<?= $fname ?>" rows="3"><?= htmlspecialchars($fval) ?></textarea>
                        <?php elseif ($ftype === 'boolean'): ?>
                            <label style="font-weight:400;display:flex;align-items:center;gap:0.4rem;">
                                <input type="checkbox" name="<?= $fname ?>" value="1" <?= $fval ? 'checked' : '' ?>> Yes
                            </label>
                        <?php elseif ($ftype === 'select'): ?>
                            <select name="<?= $fname ?>">
                                <option value="">—</option>
                                <?php foreach (($field['options'] ?? []) as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>" <?= $fval === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($ftype === 'date'): ?>
                            <input type="date" name="<?= $fname ?>" value="<?= htmlspecialchars($fval) ?>">
                        <?php elseif ($ftype === 'datetime'): ?>
                            <input type="datetime-local" name="<?= $fname ?>" value="<?= htmlspecialchars($fval) ?>">
                        <?php elseif ($ftype === 'number'): ?>
                            <input type="number" name="<?= $fname ?>" value="<?= htmlspecialchars($fval) ?>">
                        <?php else: ?>
                            <input type="text" name="<?= $fname ?>" value="<?= htmlspecialchars($fval) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
            <?php endif; ?>

            <div class="panel collapsible" data-panel="related-content">
                <button type="button" class="panel-head" aria-expanded="false">
                    <h3>Related content</h3>
                    <i class="fa-solid fa-chevron-down panel-chevron" aria-hidden="true"></i>
                </button>
                <div class="panel-body">
                <div class="form-group" style="max-height:180px;overflow:auto;">
                    <?php if (empty($relatedOptions)): ?>
                        <p style="font-size:0.85rem;color:var(--hq-muted);margin:0;">No other content yet.</p>
                    <?php else: ?>
                        <?php foreach ($relatedOptions as $opt): ?>
                            <?php if (!$isNew && (int) $opt['id'] === (int) ($content['id'] ?? 0)) continue; ?>
                            <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;font-weight:400;color:var(--hq-text);">
                                <input type="checkbox" name="related[]" value="<?= (int) $opt['id'] ?>"
                                    <?= in_array((int) $opt['id'], array_map('intval', $relatedIds), true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($opt['title']) ?>
                                <span style="color:var(--hq-muted);font-size:0.75rem;">/<?= htmlspecialchars($opt['slug']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            </div>

            <?php if (!$isNew && !empty($revisions)): ?>
            <div class="panel collapsible" data-panel="revisions">
                <button type="button" class="panel-head" aria-expanded="false">
                    <h3>Revisions</h3>
                    <i class="fa-solid fa-chevron-down panel-chevron" aria-hidden="true"></i>
                </button>
                <div class="panel-body">
                <div style="max-height:200px;overflow:auto;font-size:0.85rem;">
                    <?php foreach ($revisions as $rev): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;padding:0.4rem 0;border-bottom:1px solid var(--hq-border);">
                            <div>
                                <div><?= htmlspecialchars(date('Y-m-d H:i', strtotime($rev['created_at']))) ?></div>
                                <div style="color:var(--hq-muted);"><?= htmlspecialchars($rev['author_name'] ?? 'Unknown') ?></div>
                            </div>
                            <button type="submit" form="restore-rev-<?= (int) $rev['id'] ?>" class="btn-ghost" style="font-size:0.75rem;"
                                    onclick="return confirm('Restore this revision? Current state will be saved as a new revision.');">Restore</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            </div>
            <?php endif; ?>

            <div class="panel collapsible" data-panel="seo">
                <button type="button" class="panel-head" aria-expanded="false">
                    <h3>SEO</h3>
                    <i class="fa-solid fa-chevron-down panel-chevron" aria-hidden="true"></i>
                </button>
                <div class="panel-body">
                <div class="form-group">
                    <label>SEO Title</label>
                    <input type="text" name="seo_title" value="<?= htmlspecialchars($meta['seo_title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea name="meta_description" rows="3"><?= htmlspecialchars($meta['meta_description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Robots</label>
                    <input type="text" name="robots" value="<?= htmlspecialchars($meta['robots'] ?? 'index, follow') ?>">
                </div>
            </div>
            </div>
        
<script>
(function () {
  var layout = document.getElementById('content-form-layout');
  var sidebar = document.getElementById('content-form-sidebar');
  var btnClose = document.getElementById('btn-close-sidebar');
  var titleEl = document.getElementById('form-sidebar-title');

  var LABELS = {
    'publish': 'Publish',
    'categories': 'Categories',
    'tags': 'Tags',
    'featured-image': 'Featured image',
    'related-content': 'Related content',
    'seo': 'SEO',
    'custom-fields': 'Custom fields',
    'revisions': 'Revisions'
  };

  function closeSidebar() {
    if (layout) {
      layout.classList.add('sidebar-closed');
      layout.classList.remove('sidebar-overlay');
    }
    if (sidebar) sidebar.hidden = true;
    document.body.classList.remove('hq-sidebar-open');
    document.querySelectorAll('.form-sidebar .panel.collapsible').forEach(function (p) {
      p.classList.remove('is-open', 'is-active-tool');
      p.hidden = true;
      var head = p.querySelector('.panel-head');
      if (head) head.setAttribute('aria-expanded', 'false');
    });
    delete document.body.dataset.activeTool;
  }

  function openSidebarTool(name) {
    if (name === 'ai-assist') {
      closeSidebar();
      var ai = document.getElementById('ai-assist-panel');
      if (ai) {
        ai.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.body.dataset.activeTool = 'ai-assist';
      }
      return;
    }

    if (!layout || !sidebar) return;

    layout.classList.remove('sidebar-closed');
    sidebar.hidden = false;
    // On narrow screens present sidebar as an overlay so users notice it
    if (window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
      layout.classList.add('sidebar-overlay');
      document.body.classList.add('hq-sidebar-open');
    } else {
      layout.classList.remove('sidebar-overlay');
      document.body.classList.remove('hq-sidebar-open');
    }

    // Show only the requested panel — sidebar stays otherwise empty
    document.querySelectorAll('.form-sidebar .panel.collapsible').forEach(function (p) {
      var match = p.getAttribute('data-panel') === name;
      p.hidden = !match;
      p.classList.toggle('is-open', match);
      p.classList.toggle('is-active-tool', match);
      var head = p.querySelector('.panel-head');
      if (head) head.setAttribute('aria-expanded', match ? 'true' : 'false');
    });

    if (titleEl) titleEl.textContent = LABELS[name] || 'Tool';
    document.body.dataset.activeTool = name;

    // Bring the sidebar into view so users notice Publish / Featured image / SEO
    try { sidebar.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}

    var panel = document.querySelector('.form-sidebar .panel.collapsible[data-panel="' + name + '"]');
    if (panel) {
      var focusEl = panel.querySelector('select, input, textarea, button:not(.panel-head)');
      if (focusEl) {
        try { focusEl.focus({ preventScroll: true }); } catch (e) { focusEl.focus(); }
      }
    }
  }

  // Start empty — full-width editor, no sidebar
  closeSidebar();

  if (btnClose) {
    btnClose.addEventListener('click', function () {
      closeSidebar();
    });
  }

  // Line 2 → show only that tool in the sidebar
  document.addEventListener('hq:tool', function (e) {
    var action = (e.detail && e.detail.action) || '';
    if (!action) return;
    openSidebarTool(action);
  });

  /**
   * Shortcuts:
   * - Ctrl/Cmd+S → always save (submit form)
   * - Ctrl/Cmd+P → if not published: open Publish sidebar + set status Published
   *   so the user can edit sidebar fields then click Save. If already live: save.
   */
  document.addEventListener('keydown', function (e) {
    if (!(e.ctrlKey || e.metaKey)) return;
    var key = (e.key || '').toLowerCase();
    if (key !== 's' && key !== 'p') return;
    e.preventDefault();
    e.stopPropagation();

    var form = document.querySelector('form[method="post"][action*="content"]')
      || document.querySelector('#content-form-layout') && document.querySelector('#content-form-layout').closest('form')
      || document.querySelector('form[method="post"]');

    var statusSel = document.getElementById('status-select');
    var currentStatus = statusSel ? statusSel.value : 'draft';
    var isLive = currentStatus === 'published';

    if (key === 'p' && !isLive) {
      openSidebarTool('publish');
      if (statusSel) {
        statusSel.value = 'published';
        statusSel.dispatchEvent(new Event('change', { bubbles: true }));
      }
      var saveBtn = document.getElementById('btn-publish-save');
      if (saveBtn) {
        try { saveBtn.focus({ preventScroll: true }); } catch (err) { saveBtn.focus(); }
      }
      return;
    }

    if (form) {
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
    }
  }, true);
})();
</script>
</aside>
    </div>
</form>

<?php if (!$isNew && !empty($revisions)): ?>
    <?php foreach ($revisions as $rev): ?>
    <form id="restore-rev-<?= (int) $rev['id'] ?>" method="post" action="/hq/content/edit/<?= (int) $content['id'] ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="restore_revision">
        <input type="hidden" name="revision_id" value="<?= (int) $rev['id'] ?>">
    </form>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!$isNew): ?>
<form id="trash-form" method="post" action="/hq/content/trash/<?= (int) $content['id'] ?>">
    <?= Csrf::field() ?>
</form>
<?php endif; ?>

<!-- Media library picker -->
<div class="media-modal-overlay" id="media-modal" aria-hidden="true">
    <div class="media-modal" role="dialog" aria-modal="true" aria-labelledby="media-modal-title">
        <div class="media-modal-header">
            <h2 id="media-modal-title">Media library</h2>
            <button type="button" class="media-modal-close" id="media-modal-close" title="Close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="media-modal-toolbar">
            <label class="btn-primary" style="cursor:pointer;margin:0;">
                <i class="fa-solid fa-upload"></i> Upload
                <input type="file" id="media-picker-file" accept="image/*,.svg" multiple hidden>
            </label>
            <div class="media-modal-search">
                <input type="search" id="media-picker-search" placeholder="Search media…" autocomplete="off">
            </div>
        </div>
        <div class="media-modal-body">
            <div id="media-picker-status" class="media-modal-status"></div>
            <div class="media-picker-grid" id="media-picker-grid"></div>
        </div>
        <div class="media-modal-footer">
            <span class="selection-count" id="media-selection-count">Select an image</span>
            <div class="actions">
                <button type="button" class="btn-ghost" id="media-modal-cancel">Cancel</button>
                <button type="button" class="btn-primary" id="media-modal-insert" disabled>Insert</button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="mova-csrf" value="<?= htmlspecialchars(\Mova\Security\Csrf::token()) ?>">

<script>
(function () {
    const editor = document.getElementById('editor');
    const bodyInput = document.getElementById('body-input');
    const form = document.getElementById('content-form');
    const csrfToken = document.getElementById('mova-csrf').value;

    function focusEditor() {
        editor.focus();
    }

    function insertHTML(html) {
        focusEditor();
        // Restore a usable selection — contenteditable often loses it after modal/prompt
        let sel = window.getSelection();
        if (!sel || !sel.rangeCount || !editor.contains(sel.anchorNode)) {
            const range = document.createRange();
            range.selectNodeContents(editor);
            range.collapse(false);
            sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
        let ok = false;
        try {
            ok = document.execCommand('insertHTML', false, html);
        } catch (e) {
            ok = false;
        }
        if (!ok) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            while (tmp.firstChild) {
                editor.appendChild(tmp.firstChild);
            }
        }
        focusEditor();
        updatePlaceholder();
        if (bodyInput) bodyInput.value = editor.innerHTML;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Standard execCommand buttons — custom indent avoids blockquote/background side-effects
    function applyIndent(dir) {
        focusEditor();
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return;
        let node = sel.anchorNode;
        if (node && node.nodeType === 3) node = node.parentElement;
        while (node && node !== editor && node.nodeType === 1) {
            const tag = (node.tagName || '').toLowerCase();
            if (['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote'].indexOf(tag) !== -1) {
                break;
            }
            node = node.parentElement;
        }
        if (!node || node === editor) return;
        const step = 1.5; // rem
        const current = parseFloat(node.style.marginLeft || '0') || 0;
        const next = dir > 0 ? current + step : Math.max(0, current - step);
        if (next <= 0) {
            node.style.marginLeft = '';
            node.style.paddingLeft = '';
            // Clear any accidental background browsers may have applied via native indent
            if (node.style.backgroundColor) node.style.backgroundColor = '';
            if (node.style.background) node.style.background = '';
        } else {
            node.style.marginLeft = next + 'rem';
            // Keep typography identical to surrounding text — indent only
            node.style.background = '';
            node.style.backgroundColor = '';
            node.style.borderLeft = '';
        }
        updatePlaceholder();
        if (bodyInput) bodyInput.value = editor.innerHTML;
    }

    // ---------- Two-level toolbar (desktop groups + mobile More) ----------
    const toolbarWrap = document.getElementById('editor-toolbar-wrap');
    const line1 = document.getElementById('editor-toolbar-line1');
    const line2 = document.getElementById('editor-toolbar-line2');
    const moreBtn = document.getElementById('btn-toolbar-more');
    let activeGroup = 'text';
    let mobileMoreOpen = false;
    let mobileDrilled = false; // true when viewing a group's tools on mobile

    function isMobileToolbar() {
        return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
    }

    function setActivePanel(name) {
        if (!line2) return;
        line2.querySelectorAll('.toolbar-panel').forEach(function (panel) {
            const match = panel.getAttribute('data-panel') === name;
            panel.classList.toggle('is-active', match);
        });
        line2.setAttribute('data-active-group', name || '');
    }

    function setDesktopGroup(name) {
        activeGroup = name || 'text';
        if (line1) {
            line1.querySelectorAll('.toolbar-tab').forEach(function (tab) {
                const on = tab.getAttribute('data-group') === activeGroup;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        setActivePanel(activeGroup);
        if (toolbarWrap) {
            toolbarWrap.classList.remove('is-mobile-more-open');
        }
        mobileMoreOpen = false;
        mobileDrilled = false;
        if (moreBtn) moreBtn.setAttribute('aria-expanded', 'false');
    }

    function openMobileGroups() {
        mobileMoreOpen = true;
        mobileDrilled = false;
        if (toolbarWrap) toolbarWrap.classList.add('is-mobile-more-open');
        setActivePanel('groups');
        if (moreBtn) moreBtn.setAttribute('aria-expanded', 'true');
    }

    function closeMobileMore() {
        mobileMoreOpen = false;
        mobileDrilled = false;
        if (toolbarWrap) toolbarWrap.classList.remove('is-mobile-more-open');
        if (moreBtn) moreBtn.setAttribute('aria-expanded', 'false');
        // Keep desktop panel in sync but line2 is hidden on mobile when closed
        setActivePanel(activeGroup);
    }

    function openMobileGroup(name) {
        activeGroup = name || 'text';
        mobileDrilled = true;
        mobileMoreOpen = true;
        if (toolbarWrap) toolbarWrap.classList.add('is-mobile-more-open');
        setActivePanel(activeGroup);
        if (line1) {
            line1.querySelectorAll('.toolbar-tab').forEach(function (tab) {
                const on = tab.getAttribute('data-group') === activeGroup;
                tab.classList.toggle('is-active', on);
            });
        }
    }

    // Initial desktop state
    setDesktopGroup('text');

    if (toolbarWrap) {
        toolbarWrap.addEventListener('click', function (e) {
            const btn = e.target.closest('button');
            if (!btn) return;

            // Desktop group tabs
            if (btn.classList.contains('toolbar-tab')) {
                e.preventDefault();
                setDesktopGroup(btn.getAttribute('data-group'));
                return;
            }

            // Mobile More
            if (btn.id === 'btn-toolbar-more') {
                e.preventDefault();
                if (mobileMoreOpen && !mobileDrilled) {
                    closeMobileMore();
                } else if (mobileMoreOpen && mobileDrilled) {
                    openMobileGroups();
                } else {
                    openMobileGroups();
                }
                return;
            }

            // Mobile group pick from groups list
            if (btn.classList.contains('toolbar-group-pick')) {
                e.preventDefault();
                openMobileGroup(btn.getAttribute('data-group'));
                return;
            }

            // Mobile back → return to group list
            if (btn.hasAttribute('data-toolbar-back') || btn.classList.contains('toolbar-back')) {
                e.preventDefault();
                openMobileGroups();
                return;
            }

            // Alias buttons that share handlers
            if (btn.classList.contains('btn-link-alias') || btn.id === 'btn-link-desk') {
                e.preventDefault();
                const real = document.getElementById('btn-link');
                if (real) real.click();
                return;
            }
            if (btn.classList.contains('btn-image-alias') || btn.id === 'btn-image-desk') {
                e.preventDefault();
                const real = document.getElementById('btn-image');
                if (real) real.click();
                return;
            }
            if (btn.classList.contains('btn-embed-alias')) {
                e.preventDefault();
                const real = document.getElementById('btn-embed');
                if (real) real.click();
                return;
            }
            if (btn.classList.contains('btn-pre-alias')) {
                e.preventDefault();
                const real = document.getElementById('btn-pre');
                if (real) real.click();
                return;
            }
            if (btn.classList.contains('btn-clear-alias')) {
                e.preventDefault();
                const real = document.getElementById('btn-clear');
                if (real) real.click();
                return;
            }

            // Buttons with explicit ids are handled by their own listeners
            if (btn.id && !btn.dataset.cmd) return;

            e.preventDefault();
            const cmd = btn.dataset.cmd;
            const val = btn.dataset.value || null;
            if (cmd === 'indent') {
                applyIndent(1);
                return;
            }
            if (cmd === 'outdent') {
                applyIndent(-1);
                return;
            }
            if (cmd) {
                document.execCommand(cmd, false, val);
                focusEditor();
            }
        });
    }

    // Keep layout correct on resize
    window.addEventListener('resize', function () {
        if (!isMobileToolbar()) {
            setDesktopGroup(activeGroup || 'text');
        } else if (!mobileMoreOpen) {
            if (toolbarWrap) toolbarWrap.classList.remove('is-mobile-more-open');
        }
    });

    document.getElementById('btn-code').addEventListener('click', function (e) {
        e.preventDefault();
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        const range = sel.getRangeAt(0);
        const code = document.createElement('code');
        code.textContent = range.toString() || 'code';
        range.deleteContents();
        range.insertNode(code);
        focusEditor();
    });

    document.getElementById('btn-pre').addEventListener('click', function (e) {
        e.preventDefault();
        const sel = window.getSelection();
        const text = sel.rangeCount ? sel.toString() : '';
        insertHTML('<pre><code>' + (text ? escapeHtml(text) : 'code here') + '</code></pre><p><br></p>');
    });

    document.getElementById('btn-link').addEventListener('click', function (e) {
        e.preventDefault();
        const url = prompt('URL');
        if (!url) return;
        document.execCommand('createLink', false, url);
        focusEditor();
    });

    document.getElementById('btn-unlink').addEventListener('click', function (e) {
        e.preventDefault();
        document.execCommand('unlink', false, null);
        focusEditor();
    });

    document.getElementById('btn-video').addEventListener('click', function (e) {
        e.preventDefault();
        const url = prompt('Video URL (YouTube, Vimeo, or direct mp4)');
        if (!url) return;
        let embed = '';
        const yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
        const vimeo = url.match(/vimeo\.com\/(\d+)/);
        if (yt) {
            embed = '<div class="video-embed"><iframe src="https://www.youtube.com/embed/' + yt[1] + '" frameborder="0" allowfullscreen loading="lazy"></iframe></div>';
        } else if (vimeo) {
            embed = '<div class="video-embed"><iframe src="https://player.vimeo.com/video/' + vimeo[1] + '" frameborder="0" allowfullscreen loading="lazy"></iframe></div>';
        } else {
            embed = '<div class="video-embed"><video controls src="' + escapeHtml(url) + '"></video></div>';
        }
        insertHTML(embed + '<p><br></p>');
    });

    document.getElementById('btn-embed').addEventListener('click', function (e) {
        e.preventDefault();
        const html = prompt('Paste embed HTML (iframe, etc.)');
        if (!html) return;
        insertHTML('<div class="embed">' + html + '</div><p><br></p>');
    });

    document.getElementById('btn-callout').addEventListener('click', function (e) {
        e.preventDefault();
        insertHTML('<aside class="callout"><p>Callout text — important note or tip.</p></aside><p><br></p>');
    });

    document.getElementById('btn-table').addEventListener('click', function (e) {
        e.preventDefault();
        const rows = parseInt(prompt('Rows', '3'), 10) || 3;
        const cols = parseInt(prompt('Columns', '3'), 10) || 3;
        let html = '<table><thead><tr>';
        for (let c = 0; c < cols; c++) html += '<th>Header</th>';
        html += '</tr></thead><tbody>';
        for (let r = 0; r < Math.max(1, rows - 1); r++) {
            html += '<tr>';
            for (let c = 0; c < cols; c++) html += '<td>Cell</td>';
            html += '</tr>';
        }
        html += '</tbody></table><p><br></p>';
        insertHTML(html);
    });

    document.getElementById('btn-button').addEventListener('click', function (e) {
        e.preventDefault();
        const label = prompt('Button label', 'Learn more') || 'Learn more';
        const href = prompt('Button URL', '#') || '#';
        insertHTML('<p><a class="btn" href="' + escapeHtml(href) + '">' + escapeHtml(label) + '</a></p><p><br></p>');
    });

    document.getElementById('btn-faq').addEventListener('click', function (e) {
        e.preventDefault();
        const q = prompt('Question', 'What is…?') || 'Question?';
        insertHTML('<div class="faq-item"><h3 class="faq-question">' + escapeHtml(q) + '</h3><div class="faq-answer"><p>Answer goes here.</p></div></div><p><br></p>');
    });

    document.getElementById('btn-clear').addEventListener('click', function (e) {
        e.preventDefault();
        document.execCommand('removeFormat', false, null);
        document.execCommand('formatBlock', false, 'p');
        focusEditor();
    });

    // ---------- Media library picker ----------
    const modal = document.getElementById('media-modal');
    const grid = document.getElementById('media-picker-grid');
    const statusEl = document.getElementById('media-picker-status');
    const countEl = document.getElementById('media-selection-count');
    const insertBtn = document.getElementById('media-modal-insert');
    const searchInput = document.getElementById('media-picker-search');
    const fileInput = document.getElementById('media-picker-file');

    let pickerMode = 'image'; // 'image' | 'gallery' | 'featured'
    let allItems = [];
    let selected = new Map(); // id -> item
    let loaded = false;
    let pickerPage = 1;
    let pickerTotalPages = 1;

    function openPicker(mode) {
        pickerMode = mode || 'image';
        selected.clear();
        updateSelectionUI();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        searchInput.value = '';
        pickerPage = 1;
        loadMedia(1);
        setTimeout(function () { searchInput.focus(); }, 50);
    }

    function closePicker() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function setStatus(msg, type) {
        statusEl.textContent = msg || '';
        statusEl.className = 'media-modal-status' + (type ? ' is-' + type : '');
    }

    function loadMedia(page) {
        pickerPage = page || 1;
        setStatus('Loading…');
        grid.innerHTML = '';
        const q = (searchInput.value || '').trim();
        let url = '/hq/media/json?page=' + pickerPage;
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    setStatus(data.error || 'Failed to load media', 'error');
                    return;
                }
                allItems = data.items || [];
                pickerTotalPages = data.total_pages || 1;
                loaded = true;
                const total = data.total || allItems.length;
                setStatus(total ? ('Page ' + pickerPage + ' of ' + pickerTotalPages + ' · ' + total + ' total') : '');
                renderGrid();
            })
            .catch(function () {
                setStatus('Failed to load media', 'error');
            });
    }

    function filteredItems() {
        // Server already filters by q when provided; keep light client filter for current page
        const q = (searchInput.value || '').trim().toLowerCase();
        if (!q) return allItems;
        return allItems.filter(function (item) {
            return (item.original_name || '').toLowerCase().indexOf(q) !== -1
                || (item.alt_text || '').toLowerCase().indexOf(q) !== -1
                || (item.path || '').toLowerCase().indexOf(q) !== -1;
        });
    }

    function renderGrid() {
        const items = filteredItems();
        if (!items.length) {
            grid.innerHTML = '<div class="media-picker-empty">No media found. Upload an image to get started.</div>';
            return;
        }
        grid.innerHTML = '';
        items.forEach(function (item) {
            const el = document.createElement('div');
            el.className = 'media-picker-item' + (selected.has(item.id) ? ' is-selected' : '');
            el.dataset.id = item.id;
            const isImage = (item.mime_type || '').indexOf('image/') === 0;
            if (isImage) {
                el.innerHTML = '<img src="' + escapeHtml(item.url) + '" alt="" loading="lazy">'
                    + '<span class="media-picker-name" title="' + escapeHtml(item.original_name) + '">' + escapeHtml(item.original_name) + '</span>'
                    + '<span class="check"><i class="fa-solid fa-check"></i></span>';
            } else {
                el.innerHTML = '<div class="media-picker-placeholder">' + escapeHtml((item.extension || 'FILE').toUpperCase()) + '</div>'
                    + '<span class="media-picker-name">' + escapeHtml(item.original_name) + '</span>'
                    + '<span class="check"><i class="fa-solid fa-check"></i></span>';
            }
            el.addEventListener('click', function () {
                toggleSelect(item);
            });
            el.addEventListener('dblclick', function () {
                selected.clear();
                selected.set(item.id, item);
                updateSelectionUI();
                insertSelected();
            });
            grid.appendChild(el);
        });
        // Pagination controls (25 per page)
        if (pickerTotalPages > 1) {
            const pager = document.createElement('div');
            pager.className = 'media-picker-pager';
            pager.style.cssText = 'grid-column:1/-1;display:flex;gap:0.4rem;align-items:center;justify-content:center;padding:0.75rem 0;flex-wrap:wrap;';
            if (pickerPage > 1) {
                const prev = document.createElement('button');
                prev.type = 'button';
                prev.className = 'btn-ghost btn-sm';
                prev.textContent = '← Prev';
                prev.addEventListener('click', function () { loadMedia(pickerPage - 1); });
                pager.appendChild(prev);
            }
            const label = document.createElement('span');
            label.style.cssText = 'font-size:0.85rem;color:var(--hq-muted);';
            label.textContent = 'Page ' + pickerPage + ' / ' + pickerTotalPages;
            pager.appendChild(label);
            if (pickerPage < pickerTotalPages) {
                const next = document.createElement('button');
                next.type = 'button';
                next.className = 'btn-ghost btn-sm';
                next.textContent = 'Next →';
                next.addEventListener('click', function () { loadMedia(pickerPage + 1); });
                pager.appendChild(next);
            }
            grid.appendChild(pager);
        }
    }

    function toggleSelect(item) {
        if (pickerMode === 'image' || pickerMode === 'featured') {
            selected.clear();
            selected.set(item.id, item);
        } else {
            if (selected.has(item.id)) {
                selected.delete(item.id);
            } else {
                selected.set(item.id, item);
            }
        }
        updateSelectionUI();
        renderGrid();
    }

    function updateSelectionUI() {
        const n = selected.size;
        if (n === 0) {
            countEl.textContent = pickerMode === 'gallery' ? 'Select one or more images' : 'Select an image';
            insertBtn.disabled = true;
        } else if (n === 1) {
            countEl.textContent = '1 selected';
            insertBtn.disabled = false;
        } else {
            countEl.textContent = n + ' selected';
            insertBtn.disabled = false;
        }
    }

    function insertSelected() {
        const items = Array.from(selected.values());
        if (!items.length) return;

        if (pickerMode === 'featured') {
            const urlInput = document.getElementById('featured-image');
            if (urlInput) {
                urlInput.value = items[0].url;
            }
            const prev = document.getElementById('featured-image-preview');
            if (prev) {
                prev.style.display = 'block';
                prev.innerHTML = '<img src="' + escapeHtml(items[0].url) + '" alt="" style="max-width:100%;max-height:140px;border-radius:8px;border:1px solid var(--hq-border);object-fit:cover;">';
            }
            closePicker();
            return;
        }

        if (pickerMode === 'gallery' || items.length > 1) {
            let html = '<div class="gallery">';
            items.forEach(function (item) {
                html += '<figure><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.alt_text || '') + '" loading="lazy"></figure>';
            });
            html += '</div><p><br></p>';
            insertHTML(html);
        } else {
            const item = items[0];
            insertHTML(
                '<figure><img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.alt_text || '') + '" loading="lazy"><figcaption></figcaption></figure><p><br></p>'
            );
        }
        closePicker();
    }

    function uploadFiles(files) {
        if (!files || !files.length) return;
        setStatus('Uploading…');
        const tasks = Array.from(files).map(function (file) {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_mova_csrf', csrfToken);
            return fetch('/hq/media/upload', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); });
        });
        Promise.all(tasks)
            .then(function (results) {
                let ok = 0;
                results.forEach(function (data) {
                    if (data.success && data.media) {
                        ok++;
                        const m = data.media;
                        allItems.unshift({
                            id: m.id,
                            url: data.url,
                            path: m.path,
                            original_name: m.original_name,
                            alt_text: m.alt_text || '',
                            mime_type: m.mime_type,
                            width: m.width,
                            height: m.height,
                            extension: m.extension,
                        });
                        selected.set(m.id, allItems[0]);
                    }
                });
                if (ok) {
                    setStatus(ok + ' uploaded', 'success');
                    renderGrid();
                    updateSelectionUI();
                } else {
                    setStatus((results[0] && results[0].error) || 'Upload failed', 'error');
                }
            })
            .catch(function () {
                setStatus('Upload failed', 'error');
            });
    }

    document.getElementById('btn-image').addEventListener('click', function (e) {
        e.preventDefault();
        openPicker('image');
    });
    document.getElementById('btn-gallery').addEventListener('click', function (e) {
        e.preventDefault();
        openPicker('gallery');
    });

    // Featured image field — open picker on double-click / button
    const featuredInput = document.getElementById('featured-image');
    if (featuredInput) {
        const pickBtn = document.createElement('button');
        pickBtn.type = 'button';
        pickBtn.className = 'btn-ghost';
        pickBtn.style.cssText = 'margin-top:0.4rem;width:100%;';
        pickBtn.innerHTML = '<i class="fa-solid fa-images"></i> Choose from library';
        pickBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openPicker('featured');
        });
        featuredInput.parentNode.appendChild(pickBtn);
    }

    document.getElementById('media-modal-close').addEventListener('click', closePicker);
    document.getElementById('media-modal-cancel').addEventListener('click', closePicker);
    document.getElementById('media-modal-insert').addEventListener('click', insertSelected);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closePicker();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closePicker();
        }
    });
    let searchTimer = null;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadMedia(1); }, 250);
    });
    fileInput.addEventListener('change', function () {
        uploadFiles(this.files);
        this.value = '';
    });

    // Placeholder visibility
    function updatePlaceholder() {
        if (!editor.textContent.trim() && !editor.querySelector('img, video, iframe, table, figure')) {
            editor.classList.add('is-empty');
        } else {
            editor.classList.remove('is-empty');
        }
    }
    editor.addEventListener('input', updatePlaceholder);
    editor.addEventListener('blur', updatePlaceholder);
    updatePlaceholder();

    form.addEventListener('submit', function () {
        // Visual editor is source of truth unless Dev Mode owns name="body"
        var modeEl = document.getElementById('mova-editor-mode');
        var inDev = modeEl && modeEl.value === 'dev';
        var devHtml = document.getElementById('mova-dev-html-input');
        if (!inDev) {
            if (bodyInput) {
                bodyInput.setAttribute('name', 'body');
                bodyInput.value = editor.innerHTML;
            }
            // Dev plugin textarea must not also post as body (PHP keeps the last value)
            if (devHtml) {
                devHtml.removeAttribute('name');
            }
        }
        // Touch marker so re-saves of published content are never treated as no-ops
        var touch = document.createElement('input');
        touch.type = 'hidden';
        touch.name = '_save_touch';
        touch.value = String(Date.now());
        form.appendChild(touch);
    }, true); // capture: run before other submit handlers if possible
    // Keep hidden body in sync while typing so accidental navigation does not lose work
    editor.addEventListener('input', function () {
        if (bodyInput) bodyInput.value = editor.innerHTML;
    });

    editor.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey) {
            if (e.key === 'b') { e.preventDefault(); document.execCommand('bold'); }
            if (e.key === 'i') { e.preventDefault(); document.execCommand('italic'); }
            if (e.key === 'u') { e.preventDefault(); document.execCommand('underline'); }
            if (e.key === 'k') {
                e.preventDefault();
                const url = prompt('URL');
                if (url) document.execCommand('createLink', false, url);
            }
        }
    });

    // --- AI assist + SEO analysis ---
    const aiOut = document.getElementById('ai-output');
    const analysisOut = document.getElementById('analysis-output');
    const csrfEl = document.getElementById('mova-csrf');
    const csrf = csrfEl ? csrfEl.value : '';

    function collectContext() {
        const titleEl = form.querySelector('[name=title]');
        const excerptEl = form.querySelector('[name=excerpt]');
        const seoTitle = form.querySelector('[name=seo_title]');
        const metaDesc = form.querySelector('[name=meta_description]');
        return {
            title: titleEl ? titleEl.value : '',
            body: editor.innerHTML,
            excerpt: excerptEl ? excerptEl.value : '',
            seo_title: seoTitle ? seoTitle.value : '',
            meta_description: metaDesc ? metaDesc.value : '',
        };
    }

    document.querySelectorAll('.ai-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const action = btn.getAttribute('data-ai');
            const ctx = collectContext();
            if (aiOut) {
                aiOut.style.display = 'block';
                aiOut.textContent = 'Working…';
            }
            const fd = new FormData();
            fd.append('_mova_csrf', csrf);
            fd.append('action', action);
            fd.append('title', ctx.title);
            fd.append('body', ctx.body);
            fd.append('excerpt', ctx.excerpt);
            fetch('/hq/ai/assist', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!aiOut) return;
                    if (!data.ok) {
                        aiOut.textContent = data.error || 'Failed';
                        return;
                    }
                    let text = data.result || '';
                    if (data.provider) text += '\n\n— via ' + data.provider;
                    if (data.error) text += '\n(' + data.error + ')';
                    aiOut.textContent = text;
                })
                .catch(function () { if (aiOut) aiOut.textContent = 'Request failed'; });
        });
    });

    const btnAnalyze = document.getElementById('btn-analyze');
    if (btnAnalyze) {
        btnAnalyze.addEventListener('click', function (e) {
            e.preventDefault();
            const ctx = collectContext();
            const fd = new FormData();
            fd.append('_mova_csrf', csrf);
            fd.append('title', ctx.title);
            fd.append('body', ctx.body);
            fd.append('excerpt', ctx.excerpt);
            fd.append('seo_title', ctx.seo_title);
            fd.append('meta_description', ctx.meta_description);
            if (analysisOut) analysisOut.innerHTML = '<span style="color:var(--hq-muted)">Analyzing…</span>';
            fetch('/hq/ai/analyze', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!analysisOut) return;
                    if (!data.ok) {
                        analysisOut.textContent = data.error || 'Failed';
                        return;
                    }
                    const a = data.analysis;
                    let html = '<div style="font-size:0.875rem;"><strong>Score: ' + a.score + '/100</strong> (' + a.grade + ')';
                    html += ' · ' + a.stats.words + ' words · ' + a.stats.h2 + ' H2 · ' + a.stats.images + ' images</div>';
                    if (a.issues && a.issues.length) {
                        html += '<ul style="margin:0.5rem 0 0;padding-left:1.2rem;font-size:0.85rem;">';
                        a.issues.forEach(function (iss) {
                            html += '<li style="margin-bottom:0.25rem;">[' + iss.level + '] ' + iss.msg + '</li>';
                        });
                        html += '</ul>';
                    }
                    analysisOut.innerHTML = html;
                })
                .catch(function () { if (analysisOut) analysisOut.textContent = 'Analysis failed'; });
        });
    }

    const btnLinks = document.getElementById('btn-link-suggest');
    if (btnLinks) {
        btnLinks.addEventListener('click', function (e) {
            e.preventDefault();
            const titleEl = form.querySelector('[name=title]');
            const q = titleEl ? titleEl.value : '';
            const exclude = 0;
            if (aiOut) {
                aiOut.style.display = 'block';
                aiOut.textContent = 'Finding links…';
            }
            fetch('/hq/ai/link-suggestions?q=' + encodeURIComponent(q) + '&exclude=' + exclude)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!aiOut) return;
                    if (!data.items || !data.items.length) {
                        aiOut.textContent = 'No suggestions.';
                        return;
                    }
                    aiOut.textContent = data.items.map(function (it) {
                        return (it.related ? '★ ' : '') + it.title + ' → ' + it.url;
                    }).join('\n');
                })
                .catch(function () { if (aiOut) aiOut.textContent = 'Failed'; });
        });
    }
})();
</script>


<!-- Assembly picker modal -->
<div id="assembly-modal" hidden style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.4);align-items:center;justify-content:center;padding:1rem;">
  <div style="background:var(--hq-surface,#fff);border-radius:12px;max-width:28rem;width:100%;max-height:80vh;overflow:auto;padding:1.25rem;border:1px solid var(--hq-border,#e5e7eb);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
      <strong>Insert Assembly</strong>
      <button type="button" id="assembly-modal-close" class="btn-ghost btn-sm">Close</button>
    </div>
    <input type="search" id="assembly-modal-q" placeholder="Search published assemblies…" style="width:100%;margin-bottom:0.75rem;">
    <div id="assembly-modal-list" style="font-size:0.9rem;"></div>
  </div>
</div>
<script>
(function () {
  var btn = document.getElementById('btn-assembly');
  var modal = document.getElementById('assembly-modal');
  var list = document.getElementById('assembly-modal-list');
  var q = document.getElementById('assembly-modal-q');
  var closeBtn = document.getElementById('assembly-modal-close');
  if (!btn || !modal) return;

  function insertAtCursor(text) {
    var editor = document.getElementById('editor');
    var bodyInput = document.getElementById('body-input');
    if (editor && editor.isContentEditable) {
      editor.focus();
      var sel = window.getSelection();
      if (sel && sel.rangeCount) {
        var range = sel.getRangeAt(0);
        range.deleteContents();
        var node = document.createTextNode(text + ' ');
        range.insertNode(node);
        range.setStartAfter(node);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
      } else {
        editor.appendChild(document.createTextNode(text + ' '));
      }
      if (bodyInput) bodyInput.value = editor.innerHTML;
    } else if (bodyInput) {
      bodyInput.value += text;
    }
  }

  function load(query) {
    list.innerHTML = '<span style="color:var(--hq-muted)">Loading…</span>';
    fetch('/hq/assembly/picker?q=' + encodeURIComponent(query || ''))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok || !data.items || !data.items.length) {
          list.innerHTML = '<p style="color:var(--hq-muted);margin:0;">No published assemblies. <a href="/hq/assembly/new">Create one</a></p>';
          return;
        }
        list.innerHTML = data.items.map(function (it) {
          return '<button type="button" class="assembly-pick" data-embed="' + it.embed.replace(/"/g, '&quot;') + '" style="display:block;width:100%;text-align:left;padding:0.55rem 0.65rem;margin-bottom:0.35rem;border:1px solid var(--hq-border);border-radius:8px;background:transparent;cursor:pointer;font:inherit;">' +
            '<strong>' + it.name.replace(/</g,'&lt;') + '</strong><br><code style="font-size:0.78rem;">' + it.slug + '</code></button>';
        }).join('');
        list.querySelectorAll('.assembly-pick').forEach(function (b) {
          b.addEventListener('click', function () {
            insertAtCursor(b.getAttribute('data-embed'));
            modal.hidden = true;
            modal.style.display = 'none';
          });
        });
      })
      .catch(function () { list.innerHTML = 'Failed to load'; });
  }

  btn.addEventListener('click', function (e) {
    e.preventDefault();
    modal.hidden = false;
    modal.style.display = 'flex';
    load('');
  });
  if (closeBtn) closeBtn.addEventListener('click', function () { modal.hidden = true; modal.style.display = 'none'; });
  modal.addEventListener('click', function (e) { if (e.target === modal) { modal.hidden = true; modal.style.display = 'none'; } });
  if (q) {
    var t;
    q.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(function () { load(q.value); }, 200);
    });
  }
})();
</script>
