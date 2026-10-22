/**
 * URL of LinkedIn's post composer with the text filled in. LinkedIn doesn't document this,
 * so the share view also copies the text to the clipboard.
 * @param {String} text
 */
export function linkedinShareUrl(text) {
    return 'https://www.linkedin.com/feed/?shareActive=true&text=' + encodeURIComponent(text);
}
