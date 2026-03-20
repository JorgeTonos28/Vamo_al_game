import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-transparent text-[15px] font-semibold tracking-[0.01em] transition-all duration-100 ease-out disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-[rgba(229,184,73,0.32)] active:scale-[0.97] active:opacity-80",
  {
    variants: {
      variant: {
        default:
          "bg-[#E5B849] text-[#0A0F1D] hover:bg-[#efc55e]",
        destructive:
          "border-[rgba(248,113,113,0.3)] bg-[rgba(248,113,113,0.12)] text-[#F87171] hover:bg-[rgba(248,113,113,0.2)]",
        outline:
          "border-[rgba(255,255,255,0.08)] bg-transparent text-[#F8FAFC] hover:bg-[rgba(255,255,255,0.04)]",
        secondary:
          "bg-[#1E293B] text-[#F8FAFC] hover:bg-[#243247]",
        ghost:
          "bg-transparent text-[#94A3B8] hover:bg-[rgba(255,255,255,0.04)] hover:text-[#F8FAFC]",
        link: "h-auto min-h-0 rounded-none px-0 text-[#E5B849] underline-offset-4 hover:underline active:scale-100",
        positive:
          "border-[rgba(74,222,128,0.3)] bg-[rgba(74,222,128,0.12)] text-[#4ADE80] hover:bg-[rgba(74,222,128,0.2)]",
        "score-home":
          "border-[rgba(74,222,128,0.3)] bg-[rgba(74,222,128,0.12)] text-[#4ADE80] hover:bg-[rgba(74,222,128,0.2)] active:bg-[rgba(74,222,128,0.3)]",
        "score-away":
          "border-[rgba(229,184,73,0.3)] bg-[rgba(229,184,73,0.12)] text-[#E5B849] hover:bg-[rgba(229,184,73,0.2)] active:bg-[rgba(229,184,73,0.3)]",
      },
      size: {
        "default": "h-12 px-4 has-[>svg]:px-3",
        "sm": "h-10 gap-1.5 px-3 text-[13px] has-[>svg]:px-2.5",
        "lg": "h-12 px-6 has-[>svg]:px-4",
        "icon": "size-12 rounded-full px-0",
        "icon-sm": "size-10 rounded-full px-0",
        "icon-lg": "size-12 rounded-full px-0",
        "score": "h-[88px] rounded-xl px-5 text-base uppercase tracking-[0.08em]",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
