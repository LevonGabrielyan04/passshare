const HIBP_RANGE_URL = 'https://api.pwnedpasswords.com/range';

/**
 * @param {string} password
 * @returns {Promise<string>}
 */
async function sha1Hex(password) {
    const hashBuffer = await globalThis.crypto.subtle.digest(
        'SHA-1',
        new TextEncoder().encode(password),
    );

    return Array.from(new Uint8Array(hashBuffer))
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('')
        .toUpperCase();
}

/**
 * Check whether a password appears in the Have I Been Pwned database.
 * Uses k-anonymity: only the first five characters of the SHA-1 hash are sent.
 *
 * @param {string} password
 * @returns {Promise<boolean>}
 */
export async function isPasswordCompromised(password) {
    const hash = await sha1Hex(password);
    const prefix = hash.slice(0, 5);
    const suffix = hash.slice(5);

    const response = await fetch(`${HIBP_RANGE_URL}/${prefix}`, {
        headers: {
            'Add-Padding': 'true',
        },
    });

    if (! response.ok) {
        throw new Error('Unable to verify password.');
    }

    const body = await response.text();

    return body.split('\n').some((line) => {
        const [hashSuffix] = line.trim().split(':');

        return hashSuffix === suffix;
    });
}
