import {
    cilAccountLogout,
    cilBullhorn,
    cilCart,
    cilChartLine,
    cilCommentSquare,
    cilDescription,
    cilEnvelopeClosed,
    cilGlobeAlt,
    cilLayers,
    cilLockLocked,
    cilMenu,
    cilPeople,
    cilSearch,
    cilSettings,
    cilSpeedometer,
    cilTruck,
    cilUser,
} from '@coreui/icons';

// One icon set, imported by name so the bundle only carries what the nav uses.
// The source loaded the whole Font Awesome kit from a CDN on every page.
export const icons = {
    cilAccountLogout,
    cilBullhorn,
    cilCart,
    cilChartLine,
    cilCommentSquare,
    cilDescription,
    cilEnvelopeClosed,
    cilGlobeAlt,
    cilLayers,
    cilLockLocked,
    cilMenu,
    cilPeople,
    cilSearch,
    cilSettings,
    cilSpeedometer,
    cilTruck,
    cilUser,
};

export function icon(name) {
    return icons[name] ?? null;
}
