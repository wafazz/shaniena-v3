/**
 * `v-reveal` — a section arrives rather than appearing.
 *
 * Two rules, both deliberate:
 *
 *  - The hiding class is added by this directive, never by the markup. With
 *    JavaScript off, before the bundle runs, and in the server-rendered HTML a
 *    crawler reads, every section is simply visible. A reveal that leaves
 *    content invisible when its script fails is a broken page.
 *  - It does nothing at all when the customer has asked for reduced motion.
 *
 * Fires once per element and then disconnects: a section that re-animates
 * every time you scroll past it is a distraction on a shop.
 */
export const reveal = {
    mounted(el) {
        if (typeof window === 'undefined' || typeof IntersectionObserver === 'undefined') {
            return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        el.classList.add('reveal');

        const observer = new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting) {
                return;
            }

            el.classList.add('is-in');
            observer.disconnect();
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0.04 });

        observer.observe(el);
        el._revealObserver = observer;
    },

    unmounted(el) {
        el._revealObserver?.disconnect();
    },
};
