import { setLayoutProps } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';

/**
 * Name the record in the breadcrumbs once it is known. The edit pages are
 * rendered as a shell and fetch what they show, so their trail starts one crumb
 * short and is completed here.
 */
export function useTrailingCrumb(
    root: BreadcrumbItem,
    href: BreadcrumbItem['href'],
): (title: string) => void {
    return (title: string) => {
        setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
            breadcrumbs: [root, { title, href }],
        });
    };
}
