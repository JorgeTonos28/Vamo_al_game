<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { X } from "lucide-vue-next"
import {
  DialogClose,
  DialogContent,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"
import DialogOverlay from "./DialogOverlay.vue"

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<DialogContentProps & { class?: HTMLAttributes["class"], showCloseButton?: boolean }>(), {
  showCloseButton: true,
})
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DialogPortal>
    <DialogOverlay />
    <DialogContent
      data-slot="dialog-content"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="
        cn(
          'fixed inset-x-0 bottom-0 z-50 mx-auto grid w-full max-w-[480px] gap-4 rounded-t-[24px] border border-[rgba(255,255,255,0.08)] border-b-0 bg-[#1A243A] p-4 pb-[calc(env(safe-area-inset-bottom)+16px)] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] data-[state=open]:translate-y-0 data-[state=closed]:translate-y-full',
          props.class,
        )"
    >
      <slot />

      <DialogClose
        v-if="showCloseButton"
        data-slot="dialog-close"
        class="absolute top-4 right-4 flex size-10 items-center justify-center rounded-full bg-[#1E293B] text-[#94A3B8] transition-all duration-100 ease-out hover:text-[#F8FAFC] active:scale-[0.97] active:opacity-80 focus:ring-2 focus:ring-[rgba(229,184,73,0.2)] focus:outline-hidden disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
      >
        <X />
        <span class="sr-only">Close</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
