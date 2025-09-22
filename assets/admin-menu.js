(function() {
    'use strict';

    var matches = function(element, selector) {
        if (!element || element.nodeType !== 1) {
            return false;
        }

        var proto = element.matches || element.matchesSelector || element.msMatchesSelector || element.webkitMatchesSelector || element.mozMatchesSelector;

        if (proto) {
            return proto.call(element, selector);
        }

        var doc = element.ownerDocument || document;
        var nodes = doc.querySelectorAll(selector);
        var index = 0;

        while (nodes[index]) {
            if (nodes[index] === element) {
                return true;
            }
            index += 1;
        }

        return false;
    };

    var findClosest = function(element, selector) {
        if (!element || element.nodeType !== 1) {
            return null;
        }

        if (element.closest) {
            return element.closest(selector);
        }

        var node = element;

        while (node && node.nodeType === 1) {
            if (matches(node, selector)) {
                return node;
            }
            node = node.parentElement;
        }

        return null;
    };

    var initializeToggleRows = function() {
        var rows = document.querySelectorAll('.wma-admin-menu__submenu-row');

        if (!rows.length) {
            return;
        }

        var setOpenState = function(row, toggle, container, isOpen) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            container.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

            if (isOpen) {
                row.classList.add('is-open');
            } else {
                row.classList.remove('is-open');
            }
        };

        Array.prototype.forEach.call(rows, function(row) {
            var toggle = row.querySelector('.wma-admin-menu__submenu-toggle');
            var container = row.querySelector('.wma-admin-menu__submenu-items');

            if (!toggle || !container) {
                return;
            }

            var isInitiallyExpanded = toggle.getAttribute('aria-expanded') === 'true';
            setOpenState(row, toggle, container, isInitiallyExpanded);

            var toggleState = function(event) {
                if (event) {
                    event.stopPropagation();

                    if ('click' === event.type) {
                        event.preventDefault();
                    }
                }

                var isOpen = toggle.getAttribute('aria-expanded') === 'true';
                setOpenState(row, toggle, container, !isOpen);
            };

            toggle.addEventListener('click', toggleState);

            row.addEventListener('click', function(event) {
                var target = event.target;

                if (findClosest(target, '.wma-admin-menu__submenu-toggle')) {
                    return;
                }

                if (findClosest(target, '.wma-admin-menu__submenu-items')) {
                    return;
                }

                toggleState(event);
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeToggleRows);
    } else {
        initializeToggleRows();
    }
})();
