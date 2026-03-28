/* WP User Rights – Admin JavaScript */
/* global jQuery */
(function ($) {
    'use strict';

    $(function () {

        // Rolle wechseln: Seite mit neuer Rolle neu laden
        $('#wp-userrights-role-select').on('change', function () {
            var url = $(this).find(':selected').data('url');
            if (url) {
                window.location.href = url;
            }
        });

        // Alle Checkboxen auswählen
        $('#wp-userrights-check-all').on('click', function () {
            $('.menu-checkbox').prop('checked', true).trigger('change');
        });

        // Alle Checkboxen abwählen
        $('#wp-userrights-uncheck-all').on('click', function () {
            $('.menu-checkbox').prop('checked', false).trigger('change');
        });

        // Top-Level-Checkbox: alle Kinder mitschalten
        $(document).on('change', '.top-level-checkbox', function () {
            var parentSlug = $(this).val();
            var isChecked  = $(this).is(':checked');

            // Sub-Checkboxen mit passendem data-parent
            $('.sub-level-checkbox[data-parent="' + parentSlug + '"]').prop('checked', isChecked);
        });

        // Sub-Checkbox: wenn alle Kinder gecheckt → Top auch checken; wenn kein Kind gecheckt → Top unchecken
        $(document).on('change', '.sub-level-checkbox', function () {
            var parentSlug     = $(this).data('parent');
            var $topCheckbox   = $('.top-level-checkbox[value="' + parentSlug + '"]');
            var $siblings      = $('.sub-level-checkbox[data-parent="' + parentSlug + '"]');
            var allChecked     = $siblings.length === $siblings.filter(':checked').length;
            var anyChecked     = $siblings.filter(':checked').length > 0;

            if (allChecked) {
                $topCheckbox.prop('indeterminate', false).prop('checked', true);
            } else if (anyChecked) {
                $topCheckbox.prop('indeterminate', true).prop('checked', false);
            } else {
                $topCheckbox.prop('indeterminate', false).prop('checked', false);
            }
        });

        // Beim Laden: Indeterminate-Zustand für Top-Level initialisieren
        function initIndeterminate() {
            $('.top-level-checkbox').each(function () {
                var parentSlug = $(this).val();
                var $siblings  = $('.sub-level-checkbox[data-parent="' + parentSlug + '"]');

                if ($siblings.length === 0) {
                    // Kein Untermenü → kein indeterminate möglich
                    return;
                }

                var checkedCount = $siblings.filter(':checked').length;

                if (checkedCount > 0 && checkedCount < $siblings.length) {
                    $(this).prop('indeterminate', true).prop('checked', false);
                }
            });
        }

        // Menüpunkte ohne Kinder markieren (für CSS-Fallback bei fehlendem :has())
        function markNoChildren() {
            $('.menu-item-top').each(function () {
                if ($(this).find('.submenu-items').length === 0) {
                    $(this).addClass('no-children');
                }
            });
        }

        // Capability-Vorschau: liest alle gecheckte Slugs, schlägt deren Cap nach und zeigt sie an
        function updateCapPreview() {
            var caps    = [];
            var $box    = $('#wp-userrights-cap-preview');
            var $list   = $('#wp-userrights-cap-list');

            $('.menu-checkbox:checked').each(function () {
                var slug = $(this).val();
                // Cap aus dem passenden hidden input lesen: name="menu_cap_map[slug]"
                var cap  = $('input[name="menu_cap_map[' + slug.replace(/[[\]]/g, '\\$&') + ']"]').val();
                if (cap && caps.indexOf(cap) === -1) {
                    caps.push(cap);
                }
            });

            // 'read' ist immer dabei wenn mindestens ein Eintrag gewählt ist
            if (caps.length > 0 && caps.indexOf('read') === -1) {
                caps.unshift('read');
            }

            if (caps.length === 0) {
                $box.hide();
                return;
            }

            var html = caps.map(function (c) {
                return '<code class="cap-badge">' + c + '</code>';
            }).join(' ');

            $list.html(html);
            $box.show();
        }

        // Vorschau bei jeder Checkbox-Änderung aktualisieren
        $(document).on('change', '.menu-checkbox', function () {
            updateCapPreview();
        });

        initIndeterminate();
        markNoChildren();
        updateCapPreview(); // Beim Laden direkt aktualisieren (gespeicherte Einstellungen)
    });

}(jQuery));
