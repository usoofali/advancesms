import { onMounted, onUnmounted } from 'vue';

type Orientation = 'portrait' | 'landscape';

/**
 * Dynamically enforces print page orientation and margins in an SPA,
 * preventing @page leaks between Inertia page visits.
 */
export function usePrintOrientation(orientation: Orientation = 'portrait', margin: string = '8mm') {
    const styleId = 'spa-print-orientation-style';

    const applyStyle = () => {
        let styleEl = document.getElementById(styleId) as HTMLStyleElement | null;
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = styleId;
            document.head.appendChild(styleEl);
        }
        styleEl.textContent = `@media print { @page { size: A4 ${orientation}; margin: ${margin}; } }`;
    };

    const removeStyle = () => {
        const styleEl = document.getElementById(styleId);
        if (styleEl) {
            styleEl.remove();
        }
    };

    onMounted(() => {
        applyStyle();
    });

    onUnmounted(() => {
        removeStyle();
    });

    return {
        applyStyle,
        removeStyle,
    };
}
