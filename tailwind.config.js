import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Token warna memakai CSS variables (triplet "R G B") agar:
 * - mendukung opacity modifier Tailwind (mis. bg-brand/10)
 * - mudah di-override untuk dark mode (lihat resources/css/app.css)
 */
const withVar = (name) => `rgb(var(${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Support/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                // Aksi (oranye DMA) — hanya untuk aksi/aktif/highlight kunci.
                brand: {
                    DEFAULT: withVar('--color-brand'),
                    hover: withVar('--color-brand-hover'),
                },
                // Aksen kedalaman (navy) — dipakai hemat.
                navy: {
                    DEFAULT: withVar('--color-navy'),
                    hover: withVar('--color-navy-hover'),
                },
                // Teks.
                ink: {
                    DEFAULT: withVar('--color-ink'),
                    muted: withVar('--color-ink-muted'),
                },
                // Permukaan netral hangat.
                page: withVar('--color-page'),
                card: withVar('--color-card'),
                line: withVar('--color-line'),
                // Status pipeline (label), sengaja dibedakan dari brand.
                status: {
                    success: withVar('--color-success'),
                    pending: withVar('--color-pending'),
                    info: withVar('--color-info'),
                    danger: withVar('--color-danger'),
                },
            },

            borderRadius: {
                lg: '8px',   // kontrol
                xl: '12px',  // kartu
            },
        },
    },

    plugins: [forms],
};
