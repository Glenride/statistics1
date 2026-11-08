import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            {/* Signal waves representing data connection */}
            <path
                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"
                fill="currentColor"
                opacity="0.15"
            />
            {/* Signal bars - representing unified data sources */}
            <path
                d="M6 17V14"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <path
                d="M10 17V11"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <path
                d="M14 17V9"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <path
                d="M18 17V7"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
            />
            {/* Connection arc - representing the "signal" finding */}
            <path
                d="M6 10C8 6 16 6 18 10"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                fill="none"
                opacity="0.7"
            />
        </svg>
    );
}
