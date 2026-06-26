import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        background: "var(--background)",
        foreground: "var(--foreground)",
        primary: {
          DEFAULT: "#1877f2", // Facebook Blue
          hover: "#166fe5",
          light: "#e7f3ff",
          dark: "#1d4ed8",
        },
        card: {
          DEFAULT: "var(--card-bg)",
          border: "var(--card-border)",
        },
        accent: {
          pink: "#ff2d55",
          purple: "#af52de",
          orange: "#ff9500",
          green: "#34c759",
        }
      },
      backgroundImage: {
        "gradient-radial": "radial-gradient(var(--tw-gradient-stops))",
        "gradient-conic": "conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))",
      },
      boxShadow: {
        premium: "0 8px 30px rgb(0 0 0 / 0.12)",
        glass: "0 8px 32px 0 rgba(31, 38, 135, 0.37)",
      }
    },
  },
  plugins: [],
};
export default config;
