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

    var getTrimmedValue = function(input) {
        if (!input || typeof input.value !== 'string') {
            return '';
        }

        return input.value.replace(/\s+/g, ' ').trim();
    };

    var updateRowHighlight = function(row) {
        if (!row) {
            return;
        }

        var inputs = row.querySelectorAll('.wma-admin-menu__text-input');
        var hasValue = false;

        Array.prototype.forEach.call(inputs, function(input) {
            if (getTrimmedValue(input)) {
                hasValue = true;
            }
        });

        if (hasValue) {
            row.classList.add('has-custom-label');
        } else {
            row.classList.remove('has-custom-label');
        }
    };

    var updateInputHighlight = function(input) {
        if (!input) {
            return;
        }

        var value = getTrimmedValue(input);
        var row = findClosest(input, '.wma-admin-menu__submenu-row');
        var host = findClosest(input, '.wma-admin-menu__submenu-item');

        if (host) {
            if (value) {
                host.classList.add('has-custom-label');
            } else {
                host.classList.remove('has-custom-label');
            }

            if (row) {
                updateRowHighlight(row);
            }

            return;
        }

        host = findClosest(input, '.wma-admin-menu__menu-row');

        if (host) {
            if (value) {
                host.classList.add('has-custom-label');
            } else {
                host.classList.remove('has-custom-label');
            }

            return;
        }

        if (row) {
            if (value) {
                row.classList.add('has-custom-label');
            } else {
                row.classList.remove('has-custom-label');
            }

            updateRowHighlight(row);
        }
    };

    var openRowForInput = function(input) {
        var row = findClosest(input, '.wma-admin-menu__submenu-row');

        if (!row) {
            return;
        }

        var toggle = row.querySelector('.wma-admin-menu__submenu-toggle');
        var container = row.querySelector('.wma-admin-menu__submenu-items');

        if (!toggle || !container) {
            return;
        }

        if (toggle.getAttribute('aria-expanded') === 'true') {
            return;
        }

        toggle.setAttribute('aria-expanded', 'true');
        container.setAttribute('aria-hidden', 'false');
        row.classList.add('is-open');
    };

    var bindInputListeners = function(input, openCallback) {
        if (!input) {
            return;
        }

        var handleValueChange = function() {
            updateInputHighlight(input);
        };

        input.addEventListener('input', handleValueChange);
        input.addEventListener('change', handleValueChange);

        input.addEventListener('focus', function() {
            if (openCallback) {
                openCallback();
            } else {
                openRowForInput(input);
            }
        });

        handleValueChange();
    };

    var updateVisibilityState = function(checkbox) {
        if (!checkbox) {
            return;
        }

        var host = findClosest(checkbox, '.wma-admin-menu__menu-row');

        if (!host) {
            host = findClosest(checkbox, '.wma-admin-menu__submenu-item');
        }

        if (!host) {
            return;
        }

        if (checkbox.checked) {
            host.classList.add('is-hidden');
        } else {
            host.classList.remove('is-hidden');
        }
    };

    var initializeVisibilityCheckboxes = function() {
        var checkboxes = document.querySelectorAll('.wma-admin-menu__menu-primary input[type="checkbox"], .wma-admin-menu__submenu-item-primary input[type="checkbox"]');

        if (!checkboxes.length) {
            return;
        }

        Array.prototype.forEach.call(checkboxes, function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateVisibilityState(checkbox);
            });

            updateVisibilityState(checkbox);
        });
    };

    var initializeSortableLists = function() {
        var lists = document.querySelectorAll('[data-wma-sortable-list]');

        if (!lists.length) {
            return;
        }

        var activeItem = null;
        var pendingDragItem = null;

        var clearPendingDrag = function() {
            pendingDragItem = null;
        };

        var getDragAfterElement = function(container, y) {
            var elements = container.querySelectorAll('[data-wma-sortable-item]:not(.is-dragging)');
            var closest = { offset: Number.NEGATIVE_INFINITY, element: null };

            Array.prototype.forEach.call(elements, function(element) {
                var box = element.getBoundingClientRect();
                var offset = y - box.top - (box.height / 2);

                if (offset < 0 && offset > closest.offset) {
                    closest.offset = offset;
                    closest.element = element;
                }
            });

            return closest.element;
        };

        var handleDragStart = function(event) {
            if (!pendingDragItem || pendingDragItem !== event.currentTarget) {
                event.preventDefault();
                pendingDragItem = null;
                return;
            }

            activeItem = event.currentTarget;
            pendingDragItem = null;
            activeItem.classList.add('is-dragging');

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';

                try {
                    event.dataTransfer.setData('text/plain', '');
                } catch (error) {
                    // Some browsers (e.g., IE) may restrict setting data; ignore safely.
                }
            }
        };

        var handleDragEnd = function() {
            if (activeItem) {
                activeItem.classList.remove('is-dragging');
                activeItem = null;
            }

            pendingDragItem = null;
        };

        Array.prototype.forEach.call(lists, function(list) {
            var items = list.querySelectorAll('[data-wma-sortable-item]');

            Array.prototype.forEach.call(items, function(item) {
                item.setAttribute('draggable', 'true');

                var handle = item.querySelector('[data-wma-drag-handle]');

                if (handle) {
                    var prepare = function() {
                        pendingDragItem = item;
                    };

                    handle.addEventListener('mousedown', prepare);
                    handle.addEventListener('touchstart', prepare);
                }

                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd);
            });

            list.addEventListener('dragover', function(event) {
                if (!activeItem) {
                    return;
                }

                event.preventDefault();

                var afterElement = getDragAfterElement(list, event.clientY);

                if (!afterElement) {
                    list.appendChild(activeItem);
                } else if (afterElement !== activeItem) {
                    list.insertBefore(activeItem, afterElement);
                }

                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
            });

            list.addEventListener('drop', function(event) {
                if (activeItem) {
                    event.preventDefault();
                }
            });
        });

        document.addEventListener('mouseup', clearPendingDrag);
        document.addEventListener('touchend', clearPendingDrag);
        document.addEventListener('touchcancel', clearPendingDrag);
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

                if (findClosest(target, '.wma-admin-menu__submenu-parent-field')) {
                    return;
                }

                if (findClosest(target, '.wma-admin-menu__submenu-item-field')) {
                    return;
                }

                if (findClosest(target, '.wma-admin-menu__text-input')) {
                    return;
                }

                toggleState(event);
            });

            var textInputs = row.querySelectorAll('.wma-admin-menu__text-input');

            Array.prototype.forEach.call(textInputs, function(input) {
                bindInputListeners(input, function() {
                    setOpenState(row, toggle, container, true);
                    updateRowHighlight(row);
                });
            });

            updateRowHighlight(row);
        });
    };

    var initializeMenuInputs = function() {
        var inputs = document.querySelectorAll('.wma-admin-menu__menu-row .wma-admin-menu__text-input');

        if (!inputs.length) {
            return;
        }

        Array.prototype.forEach.call(inputs, function(input) {
            bindInputListeners(input);
        });
    };

    var init = function() {
        initializeSortableLists();
        initializeToggleRows();
        initializeMenuInputs();
        initializeVisibilityCheckboxes();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
