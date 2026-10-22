import {describe, expect, test} from 'vitest';
import {linkedinShareUrl} from '../../resources/js/linkedin.js';

describe('linkedinShareUrl', () => {
    test('encodes line breaks, hashtags and ampersands', () => {
        expect(linkedinShareUrl('Hi & bye\n\n#ai #news\nhttps://example.com/a?b=1&c=2'))
            .toBe('https://www.linkedin.com/feed/?shareActive=true&text=Hi%20%26%20bye%0A%0A%23ai%20%23news%0Ahttps%3A%2F%2Fexample.com%2Fa%3Fb%3D1%26c%3D2');
    });
});
