/* WP User Rights – Admin JavaScript
   Author: Friederich Loheide · loheide.eu */
/* global jQuery */
(function ($) {
    'use strict';

    $(function () {

        // --------------------------------------------------------------------
        // Rollenauswahl: Seite neu laden
        // --------------------------------------------------------------------
        $('#wp-userrights-role-select').on('change', function () {
            var url = $(this).find(':selected').data('url');
            if (url) {
                window.location.href = url;
            }
        });

        // --------------------------------------------------------------------
        // Alle auswählen / abwählen
        // --------------------------------------------------------------------
        $('#wp-userrights-check-all').on('click', function () {
            $('.menu-checkbox').prop('checked', true).prop('indeterminate', false);
            updateAll();
        });

        $('#wp-userrights-uncheck-all').on('click', function () {
            $('.menu-checkbox').prop('checked', false).prop('indeterminate', false);
            updateAll();
        });

        // --------------------------------------------------------------------
        // Top-Level-Checkbox: alle sichtbaren Kinder mitschalten
        // --------------------------------------------------------------------
        $(document).on('change', '.top-level-checkbox', function () {
            var parentSlug = $(this).val();
            var isChecked  = $(this).is(':checked');
            $('.sub-level-checkbox[data-parent="' + CSS.escape(parentSlug) + '"]').prop('checked', isChecked);
            updateAll();
        });

        // --------------------------------------------------------------------
        // Sub-Checkbox: Indeterminate-Zustand des Parents setzen
        // --------------------------------------------------------------------
        $(document).on('change', '.sub-level-checkbox', function () {
            syncParentState($(this).data('parent'));
            updateAll();
        });

        function syncParentState(parentSlug) {
            var $top      = $('.top-level-checkbox[value="' + CSS.escape(parentSlug) + '"]');
            var $siblings = $('.sub-level-checkbox[data-parent="' + CSS.escape(parentSlug) + '"]');
            if ($siblings.length === 0) { return; }
            var checked = $siblings.filter(':checked').length;
            if (checked === $siblings.length) {
                $top.prop('indeterminate', false).prop('checked', true);
            } else if (checked > 0) {
                $top.prop('indeterminate', true).prop('checked', false);
            } else {
                $top.prop('indeterminate', false).prop('checked', false);
            }
        }

        // --------------------------------------------------------------------
        // Zähler aktualisieren
        // --------------------------------------------------------------------
        function updateCounter() {
            var count = $('.menu-checkbox:checked').length;
            $('#wp-userrights-count-num').text(count);
        }

        // --------------------------------------------------------------------
        // Capability-Vorschau
        // --------------------------------------------------------------------
        function updateCapPreview() {
            var caps  = [];
            var $box  = $('#wp-userrights-cap-preview');
            var $list = $('#wp-userrights-cap-list');

            $('.menu-checkbox:checked').each(function () {
                var slug = $(this).val();
                var cap  = $('input[name="menu_cap_map[' + CSS.escape(slug) + ']"]').val();
                if (cap && caps.indexOf(cap) === -1) {
                    caps.push(cap);
                }
            });

            if (caps.length > 0 && caps.indexOf('read') === -1) {
                caps.unshift('read');
            }

            if (caps.length === 0) {
                $box.hide();
                return;
            }

            $list.html(
                caps.map(function (c) {
                    return '<code class="cap-badge">' + escHtml(c) + '</code>';
                }).join(' ')
            );
            $box.show();
        }

        function updateAll() {
            updateCounter();
            updateCapPreview();
        }

        // --------------------------------------------------------------------
        // Suchfilter
        // --------------------------------------------------------------------
        $('#wp-userrights-search').on('input', function () {
            var term = $(this).val().trim().toLowerCase();
            var $noResults = $('.wpur-no-results');

            // Highlight-Markierungen zurücksetzen
            $('.menu-item-label .menu-label-text').each(function () {
                $(this).html(escHtml($(this).data('original') || $(this).text()));
            });

            if (term === '') {
                $('.menu-item-top').removeClass('wpur-hidden');
                $noResults.remove();
                return;
            }

            var visibleCount = 0;

            $('.menu-item-top').each(function () {
                var $top     = $(this);
                var topText  = $top.find('.top-level-checkbox').closest('label').find('.menu-label-text').data('original') || '';
                var topSlug  = $top.find('.top-level-checkbox').val() || '';
                var topMatch = topText.toLowerCase().indexOf(term) !== -1 || topSlug.toLowerCase().indexOf(term) !== -1;

                // Kinder prüfen
                var childMatch = false;
                $top.find('.sub-level-checkbox').each(function () {
                    var $sub      = $(this).closest('label');
                    var subText   = $sub.find('.menu-label-text').data('original') || '';
                    var subSlug   = $(this).val() || '';
                    if (subText.toLowerCase().indexOf(term) !== -1 || subSlug.toLowerCase().indexOf(term) !== -1) {
                        childMatch = true;
                    }
                });

                if (topMatch || childMatch) {
                    $top.removeClass('wpur-hidden');
                    visibleCount++;
                    // Highlight im Label
                    if (topMatch) { highlightText($top.find('.top-level-checkbox').closest('label').find('.menu-label-text'), term); }
                    if (childMatch) {
                        $top.find('.sub-level-checkbox').each(function () {
                            var $sub    = $(this).closest('label');
                            var subText = $sub.find('.menu-label-text').data('original') || '';
                            if (subText.toLowerCase().indexOf(term) !== -1) {
                                highlightText($sub.find('.menu-label-text'), term);
                            }
                        });
                    }
                } else {
                    $top.addClass('wpur-hidden');
                }
            });

            $noResults.remove();
            if (visibleCount === 0) {
                $('.wp-userrights-menu-tree').after('<p class="wpur-no-results">Keine Einträge gefunden für „' + escHtml(term) + '"</p>');
            }
        });

        function highlightText($el, term) {
            var original = $el.data('original') || $el.text();
            $el.data('original', original);
            var escaped  = escHtml(original);
            var escapedTerm = escHtml(term);
            var re       = new RegExp('(' + escapedTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            $el.html(escaped.replace(re, '<span class="wpur-highlight">$1</span>'));
        }

        // --------------------------------------------------------------------
        // Initialisierung
        // --------------------------------------------------------------------

        // Label-Texte für Suche vorbereiten: .menu-label-text-Wrapper hinzufügen
        $('.menu-item-label').each(function () {
            var $label = $(this);
            // Textknoten (das eigentliche Label-Wort) in ein span wickeln
            $label.contents().filter(function () {
                return this.nodeType === 3 && this.nodeValue.trim().length > 0;
            }).each(function () {
                var text = this.nodeValue;
                $(this).replaceWith('<span class="menu-label-text" data-original="' + escHtml(text.trim()) + '">' + escHtml(text.trim()) + '</span>');
            });
        });

        // Indeterminate-Zustand initialisieren
        $('.top-level-checkbox').each(function () {
            syncParentState($(this).val());
        });

        // Menüpunkte ohne Kinder markieren
        $('.menu-item-top').each(function () {
            if ($(this).find('.submenu-items').length === 0) {
                $(this).addClass('no-children');
            }
        });

        // Initiale Aktualisierung
        updateAll();

        // --------------------------------------------------------------------
        // Hilfsfunktion: HTML escapen
        // --------------------------------------------------------------------
        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

    });

}(jQuery));
