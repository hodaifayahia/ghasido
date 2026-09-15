import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

/**
 * An entry in the app sidebar (NavMain). Unlike NavItem the href is optional:
 * a section with no route yet renders as a button that announces "coming
 * soon", and `action: 'logout'` posts to the logout route.
 */
export type SidebarNavItem = Omit<NavItem, 'href' | 'icon'> & {
    href?: NavItem['href'];
    /** A Lucide icon, or one of the solid glyphs in components/icons. */
    icon?: LucideIcon | Component;
    action?: 'logout';
    /** Other routes that also mark this entry active (e.g. every settings tab). */
    activeFor?: NavItem['href'][];
    /**
     * Extra classes for the icon. Lucide glyphs are outlines; the mockup's are
     * solid, so most entries fill the glyph and re-stroke its inner details.
     */
    iconClass?: HTMLAttributes['class'];
    /** Extra classes for the label, e.g. to keep a long name on one line. */
    labelClass?: HTMLAttributes['class'];
};
