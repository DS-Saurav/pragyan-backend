

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const initializePublicNavigation = () => {
    const header = document.querySelector('[data-public-header]');

    if (! header) {
        return;
    }

    const mobileTrigger = header.querySelector('[data-mobile-nav-trigger]');
    const mobilePanel = header.querySelector('[data-mobile-nav]');
    const desktopTriggers = [...header.querySelectorAll('[data-nav-group-trigger]')];

    const controlledPanel = (trigger) => document.getElementById(trigger.getAttribute('aria-controls'));

    const closeDesktopMenus = (restoreFocus = false) => {
        desktopTriggers.forEach((trigger) => {
            const panel = controlledPanel(trigger);
            const wasOpen = trigger.getAttribute('aria-expanded') === 'true';

            trigger.setAttribute('aria-expanded', 'false');
            panel?.setAttribute('hidden', '');

            if (restoreFocus && wasOpen) {
                trigger.focus();
            }
        });
    };

    const closeMobileMenu = (restoreFocus = false) => {
        if (! mobileTrigger || ! mobilePanel) {
            return;
        }

        const wasOpen = mobileTrigger.getAttribute('aria-expanded') === 'true';
        mobileTrigger.setAttribute('aria-expanded', 'false');
        mobileTrigger.setAttribute(
            'aria-label',
            mobileTrigger.dataset.openLabel ?? 'Open navigation menu',
        );
        mobilePanel.setAttribute('hidden', '');

        if (restoreFocus && wasOpen) {
            mobileTrigger.focus();
        }
    };

    desktopTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const panel = controlledPanel(trigger);
            const isOpen = trigger.getAttribute('aria-expanded') === 'true';

            closeDesktopMenus();
            closeMobileMenu();

            if (! isOpen) {
                trigger.setAttribute('aria-expanded', 'true');
                panel?.removeAttribute('hidden');
            }
        });
    });

    mobileTrigger?.addEventListener('click', () => {
        const isOpen = mobileTrigger.getAttribute('aria-expanded') === 'true';

        closeDesktopMenus();

        if (isOpen) {
            closeMobileMenu();

            return;
        }

        mobileTrigger.setAttribute('aria-expanded', 'true');
        mobileTrigger.setAttribute(
            'aria-label',
            mobileTrigger.dataset.closeLabel ?? 'Close navigation menu',
        );
        mobilePanel?.removeAttribute('hidden');
    });

    header.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const desktopMenuIsOpen = desktopTriggers.some(
            (trigger) => trigger.getAttribute('aria-expanded') === 'true',
        );
        const mobileMenuIsOpen = mobileTrigger?.getAttribute('aria-expanded') === 'true';

        if (! desktopMenuIsOpen && ! mobileMenuIsOpen) {
            return;
        }

        event.preventDefault();

        if (mobileMenuIsOpen) {
            closeMobileMenu(true);

            return;
        }

        closeDesktopMenus(true);
    });

    document.addEventListener('pointerdown', (event) => {
        if (! header.contains(event.target)) {
            closeDesktopMenus();
            closeMobileMenu();
        }
    });

    window.addEventListener('resize', () => {
        closeDesktopMenus();
        closeMobileMenu();
    });
};

initializePublicNavigation();
