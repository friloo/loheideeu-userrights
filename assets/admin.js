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
        // Tab: Rollen – Slug-Autogenerierung aus dem Rollennamen
        // --------------------------------------------------------------------
        $('#wpur-role-name').on('input', function () {
            var name = $(this).val();
            var slug = name
                .toLowerCase()
                .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            $('#wpur-role-slug').val(slug);
        });

        // --------------------------------------------------------------------
        // Tab: Benutzer – Bulk-Auswahl (Alle auswählen + Zähler)
        // --------------------------------------------------------------------
        function updateBulkCount() {
            var count = $('.wpur-bulk-checkbox:checked').length;
            $('#wpur-bulk-count').text(count);
            // Zeilen hervorheben
            $('.wpur-bulk-checkbox').each(function () {
                $(this).closest('tr').toggleClass('wpur-row-selected', $(this).prop('checked'));
            });
        }

        // Alle-auswählen Checkbox
        $(document).on('change', '#wpur-select-all', function () {
            var checked = $(this).prop('checked');
            $('.wpur-bulk-checkbox').prop('checked', checked);
            updateBulkCount();
        });

        // Einzelne Checkboxen
        $(document).on('change', '.wpur-bulk-checkbox', function () {
            var total   = $('.wpur-bulk-checkbox').length;
            var checked = $('.wpur-bulk-checkbox:checked').length;
            $('#wpur-select-all').prop('indeterminate', checked > 0 && checked < total)
                                 .prop('checked', checked === total && total > 0);
            updateBulkCount();
        });

        // Bulk-Formular absenden: Validierung
        $('#wpur-bulk-form').on('submit', function (e) {
            var count = $('.wpur-bulk-checkbox:checked').length;
            var role  = $('[name="bulk_role"]').val();
            if (!count || !role) {
                e.preventDefault();
                alert(count ? 'Bitte eine Rolle auswählen.' : 'Bitte mindestens einen Benutzer auswählen.');
            }
        });

        // --------------------------------------------------------------------
        // Tab: Benutzer – Suchfeld (Debounce → Auto-Submit)
        // --------------------------------------------------------------------
        var userSearchTimer;
        $('#wpur-user-search').on('input', function () {
            clearTimeout(userSearchTimer);
            userSearchTimer = setTimeout(function () {
                var $form = $('#wpur-user-search').closest('form');
                if ($form.length) { $form.submit(); }
            }, 400);
        });

        // --------------------------------------------------------------------
        // Tab: Benutzer – Chip-Dropdown öffnen/schließen
        // --------------------------------------------------------------------
        $(document).on('click', '.wpur-add-role-btn', function (e) {
            e.stopPropagation();
            var $dropdown = $(this).siblings('.wpur-role-dropdown');
            var isOpen = $dropdown.hasClass('wpur-dropdown-open');
            // Alle anderen schließen
            $('.wpur-role-dropdown.wpur-dropdown-open').removeClass('wpur-dropdown-open');
            if (!isOpen) {
                $dropdown.addClass('wpur-dropdown-open');
            }
        });

        // Außerhalb klicken → alle Dropdowns schließen
        $(document).on('click', function () {
            $('.wpur-role-dropdown.wpur-dropdown-open').removeClass('wpur-dropdown-open');
        });

        $(document).on('click', '.wpur-role-dropdown', function (e) {
            e.stopPropagation();
        });

        // --------------------------------------------------------------------
        // Tab: Benutzer – Rolle hinzufügen (Chip erstellen)
        // --------------------------------------------------------------------
        $(document).on('click', '.wpur-role-option', function () {
            if (typeof wpurData === 'undefined') { return; }

            var $btn    = $(this);
            var userId  = $btn.data('user-id');
            var role    = $btn.data('role');
            var roleName = $btn.text();
            var $chips  = $btn.closest('.wpur-role-chips');

            // Dropdown schließen
            $btn.closest('.wpur-role-dropdown').removeClass('wpur-dropdown-open');

            // Saving-Indicator einfügen
            var $saving = $('<span class="wpur-chip-saving"><span class="dashicons dashicons-update wpur-spin"></span></span>').insertBefore($chips.find('.wpur-add-role-wrap'));

            $.post(wpurData.ajaxurl, {
                action:   'wpur_toggle_user_role',
                nonce:    wpurData.nonce,
                user_id:  userId,
                role:     role,
                assigned: '1'
            })
            .done(function (res) {
                $saving.remove();
                if (res.success) {
                    // Chip einfügen
                    var $chip = $(
                        '<span class="wpur-chip" data-role="' + escHtml(role) + '">' +
                            escHtml(roleName) +
                            '<button type="button" class="wpur-chip-remove" data-user-id="' + escHtml(String(userId)) + '" data-role="' + escHtml(role) + '">&times;</button>' +
                        '</span>'
                    );
                    $chip.insertBefore($chips.find('.wpur-add-role-wrap'));
                    // Option aus Dropdown entfernen
                    $btn.remove();
                } else {
                    alert(wpurData.error);
                }
            })
            .fail(function () {
                $saving.remove();
                alert(wpurData.error);
            });
        });

        // --------------------------------------------------------------------
        // Tab: Benutzer – Rolle entfernen (Chip löschen)
        // --------------------------------------------------------------------
        $(document).on('click', '.wpur-chip-remove', function () {
            if (typeof wpurData === 'undefined') { return; }

            var $btn    = $(this);
            var $chip   = $btn.closest('.wpur-chip');
            var userId  = $btn.data('user-id');
            var role    = $btn.data('role');
            var roleName = $chip.clone().children().remove().end().text().trim();
            var $chips  = $chip.closest('.wpur-role-chips');

            // Visuelles Feedback
            $chip.css('opacity', '0.5');

            $.post(wpurData.ajaxurl, {
                action:   'wpur_toggle_user_role',
                nonce:    wpurData.nonce,
                user_id:  userId,
                role:     role,
                assigned: '0'
            })
            .done(function (res) {
                if (res.success) {
                    $chip.remove();
                    // Option wieder ins Dropdown einfügen
                    var $option = $(
                        '<button type="button" class="wpur-role-option" data-user-id="' + escHtml(String(userId)) + '" data-role="' + escHtml(role) + '">' +
                            escHtml(roleName) +
                        '</button>'
                    );
                    $chips.find('.wpur-role-dropdown').append($option);
                } else {
                    $chip.css('opacity', '1');
                    alert(wpurData.error);
                }
            })
            .fail(function () {
                $chip.css('opacity', '1');
                alert(wpurData.error);
            });
        });

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
