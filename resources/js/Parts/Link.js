import { h } from 'vue';
import {Link} from "@inertiajs/vue3";

const configuredBase = document.querySelector('base').href;

/**
 * This is a custom link component which wraps the default inertia link component
 * so that we can intercept it to convert any string hrefs into URL objects
 * that have the expected base URL.
 *
 * By default, inertia does not provide control over the base URL or consider the
 * based set in HTML for the page.
 *
 * Related to:
 * https://github.com/inertiajs/inertia/issues/1214
 */

const CustomLink = (props, {attrs, slots}) => {
    let href = props.href;
    if (typeof href === 'string') {
        href = new URL(href, configuredBase || window.location.href);
    }

    return h(Link, {
        ...attrs,
        href: href,
    }, slots);
};

export default CustomLink;