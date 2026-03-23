<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { hydrateBrandingState, brandingState } from '@/state/branding'

const props = withDefaults(
  defineProps<{
    compact?: boolean
    centered?: boolean
  }>(),
  {
    compact: false,
    centered: false,
  },
)

const branding = computed(() => brandingState.branding)

onMounted(() => {
  void hydrateBrandingState()
})
</script>

<template>
  <div :class="['brand-logo', { 'is-centered': props.centered, 'is-compact': props.compact }]">
    <img
      v-if="branding?.has_custom_logo && branding.logo_url"
      :src="branding.logo_url"
      alt="Vamo al Game"
      :class="['brand-logo__image', { 'is-compact': props.compact }]"
    />
    <template v-else>
      <div class="brand-logo__mark">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 42" class="brand-logo__icon">
          <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M17.2 5.633 8.6.855 0 5.633v26.51l16.2 9 16.2-9v-8.442l7.6-4.223V9.856l-8.6-4.777-8.6 4.777V18.3l-5.6 3.111V5.633ZM38 18.301l-5.6 3.11v-6.157l5.6-3.11V18.3Zm-1.06-7.856-5.54 3.078-5.54-3.079 5.54-3.078 5.54 3.079ZM24.8 18.3v-6.157l5.6 3.111v6.158L24.8 18.3Zm-1 1.732 5.54 3.078-13.14 7.302-5.54-3.078 13.14-7.3v-.002Zm-16.2 7.89 7.6 4.222V38.3L2 30.966V7.92l5.6 3.111v16.892ZM8.6 9.3 3.06 6.222 8.6 3.143l5.54 3.08L8.6 9.3Zm21.8 15.51-13.2 7.334V38.3l13.2-7.334v-6.156ZM9.6 11.034l5.6-3.11v14.6l-5.6 3.11v-14.6Z"
          />
        </svg>
      </div>

      <div class="brand-logo__copy">
        <span class="brand-logo__title">Vamo</span>
        <span class="brand-logo__subtitle">al game</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.brand-logo {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.brand-logo.is-centered {
  justify-content: center;
  text-align: center;
}

.brand-logo__mark {
  display: flex;
  height: 44px;
  width: 44px;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  background: #e5b849;
  color: #0a0f1d;
}

.brand-logo__image {
  display: block;
  height: 52px;
  width: auto;
  max-width: min(100%, 220px);
  object-fit: contain;
}

.brand-logo__image.is-compact {
  height: 40px;
  max-width: min(100%, 180px);
}

.brand-logo__icon {
  height: 22px;
  width: 22px;
}

.brand-logo__copy {
  display: grid;
  text-align: left;
}

.brand-logo__title {
  font-family: var(--font-display), ui-sans-serif, sans-serif;
  font-size: 28px;
  line-height: 0.9;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #e5b849;
}

.brand-logo__subtitle {
  font-size: 11px;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: #94a3b8;
}

.brand-logo.is-compact {
  gap: 8px;
}

.brand-logo.is-compact .brand-logo__mark {
  height: 38px;
  width: 38px;
  border-radius: 12px;
}

.brand-logo.is-compact .brand-logo__icon {
  height: 18px;
  width: 18px;
}

.brand-logo.is-compact .brand-logo__title {
  font-size: 22px;
}

.brand-logo.is-compact .brand-logo__subtitle {
  font-size: 10px;
  letter-spacing: 0.18em;
}
</style>
