<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground selection:bg-[#E5B849] selection:text-[#0A0F1D] h-12 w-full min-w-0 rounded-xl border border-[rgba(255,255,255,0.06)] bg-[#0E1628] px-4 text-base text-[#F8FAFC] transition-[border-color,background-color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-[15px]',
      'focus-visible:border-[rgba(229,184,73,0.35)] focus-visible:ring-2 focus-visible:ring-[rgba(229,184,73,0.2)]',
      'aria-invalid:border-[rgba(248,113,113,0.5)] aria-invalid:ring-2 aria-invalid:ring-[rgba(248,113,113,0.15)]',
      props.class,
    )"
  >
</template>
