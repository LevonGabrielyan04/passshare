import { afterEach, describe, expect, it, vi } from 'vitest';
import { isPasswordCompromised } from './isPasswordCompromised.js';

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('isPasswordCompromised', () => {
    it('returns true when the password hash suffix is present in the HIBP response', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            text: async () => '1E4C9B93F3F0682250B6CF8331B7EE68FD8:3861493',
        }));

        await expect(isPasswordCompromised('password')).resolves.toBe(true);

        expect(fetch).toHaveBeenCalledWith(
            'https://api.pwnedpasswords.com/range/5BAA6',
            {
                headers: {
                    'Add-Padding': 'true',
                },
            },
        );
    });

    it('returns false when the password hash suffix is not present in the HIBP response', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            text: async () => '000000000000000000000000000000000000:1',
        }));

        await expect(isPasswordCompromised('password')).resolves.toBe(false);
    });

    it('throws when the HIBP API request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            text: async () => '',
        }));

        await expect(isPasswordCompromised('password')).rejects.toThrow('Unable to verify password.');
    });
});
