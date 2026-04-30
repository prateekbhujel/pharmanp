import { useEffect, useRef } from 'react';

const FOCUSABLE_SELECTOR = [
    'input:not([type="hidden"]):not([disabled]):not([readonly])',
    'textarea:not([disabled]):not([readonly])',
    '.ant-select:not(.ant-select-disabled) .ant-select-selector',
    '.ant-picker:not(.ant-picker-disabled) input:not([disabled]):not([readonly])',
    '[data-keyboard-focus="true"]',
].join(',');

function isVisible(element) {
    if (!element) return false;
    const rect = element.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0 && window.getComputedStyle(element).visibility !== 'hidden';
}

function uniqueElements(elements) {
    return elements.filter((element, index) => elements.indexOf(element) === index);
}

export function keyboardFocusable(container) {
    if (!container) return [];

    return uniqueElements(Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR)))
        .filter((element) => isVisible(element) && !element.closest('[data-keyboard-skip="true"]'));
}

export function focusKeyboardElement(element) {
    if (!element) return false;

    element.focus({ preventScroll: false });

    if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
        element.select?.();
    }

    return true;
}

export function focusFirstKeyboardField(container, preferredSelector = null) {
    window.setTimeout(() => {
        if (!container) return;
        const preferred = preferredSelector ? container.querySelector(preferredSelector) : null;
        if (preferred && isVisible(preferred)) {
            focusKeyboardElement(preferred);
            return;
        }

        focusKeyboardElement(keyboardFocusable(container)[0]);
    }, 80);
}

export function focusNextKeyboardField(target, direction = 1) {
    const container = target?.closest?.('[data-keyboard-flow="true"]') || target?.form || document;
    const focusable = keyboardFocusable(container);
    if (!focusable.length) return false;

    const activeSelect = target?.closest?.('.ant-select');
    const current = activeSelect?.querySelector('.ant-select-selector') || target;
    const index = focusable.findIndex((element) => element === current || element.contains(current));
    const next = focusable[index + direction];

    return focusKeyboardElement(next);
}

function hasOpenPopup() {
    return Boolean(
        document.querySelector('.ant-select-dropdown:not(.ant-select-dropdown-hidden)')
        || document.querySelector('.ant-picker-dropdown:not(.ant-picker-dropdown-hidden)')
    );
}

function shouldLetElementHandleEnter(target) {
    if (!target) return true;
    if (target.tagName === 'TEXTAREA') return true;
    if (target.closest('.ant-select')) return true;
    if (target.closest('.ant-picker') && hasOpenPopup()) return true;
    if (target.closest('button,a,[role="button"]')) return true;
    return false;
}

export function useKeyboardFlow(
    containerRef,
    {
        enabled = true,
        onAddRow,
        onSubmit,
        autofocus = false,
        autofocusSelector = null,
        resetKey,
    } = {},
) {
    const callbacks = useRef({ onAddRow, onSubmit });
    callbacks.current = { onAddRow, onSubmit };

    useEffect(() => {
        if (!enabled || !autofocus) return;
        focusFirstKeyboardField(containerRef.current, autofocusSelector);
    }, [enabled, autofocus, autofocusSelector, resetKey, containerRef]);

    useEffect(() => {
        const container = containerRef.current;
        if (!enabled || !container) return undefined;

        function handleKeyDown(event) {
            if (event.defaultPrevented || event.isComposing) return;

            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                callbacks.current.onSubmit?.();
                return;
            }

            if ((event.ctrlKey || event.metaKey) && (event.key === '+' || event.key === '=')) {
                event.preventDefault();
                callbacks.current.onAddRow?.();
                return;
            }

            if (event.key !== 'Enter' || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }

            const target = event.target;
            if (shouldLetElementHandleEnter(target)) return;

            event.preventDefault();
            const moved = focusNextKeyboardField(target, event.shiftKey ? -1 : 1);

            if (!moved && callbacks.current.onAddRow && target.closest('.transaction-lines')) {
                callbacks.current.onAddRow();
                window.setTimeout(() => {
                    const rows = container.querySelectorAll('.transaction-lines tbody tr:not(.line-error-row)');
                    focusFirstKeyboardField(rows[rows.length - 1] || container.querySelector('.transaction-lines'));
                }, 40);
            }
        }

        container.addEventListener('keydown', handleKeyDown);
        return () => container.removeEventListener('keydown', handleKeyDown);
    }, [containerRef, enabled]);
}
