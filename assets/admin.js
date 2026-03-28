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

        initIndeterminate();
        markNoChildren();
    });

}(jQuery));
