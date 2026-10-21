<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { Check } from "@lucide/vue"
import { computed } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<
  CheckboxRootProps & {
    class?: HTMLAttributes["class"]
    checked?: boolean | "indeterminate" | null
  }
>()

const emits = defineEmits<
  CheckboxRootEmits & {
    "update:checked": [value: boolean]
  }
>()

const delegatedProps = reactiveOmit(props, "class", "checked", "modelValue")

const forwarded = useForwardPropsEmits(delegatedProps)

const resolvedModelValue = computed(() => {
  if (props.modelValue !== undefined) {
    return props.modelValue
  }
  if (props.checked !== undefined) {
    return props.checked ?? false
  }
  return undefined
})

function handleUpdateModelValue(val: boolean | "indeterminate") {
  emits("update:modelValue", val)
  emits("update:checked", val === "indeterminate" ? false : Boolean(val))
}
</script>

<template>
  <CheckboxRoot
    v-slot="slotProps"
    data-slot="checkbox"
    v-bind="forwarded"
    :model-value="resolvedModelValue"
    @update:model-value="handleUpdateModelValue"
    :class="
      cn('peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
         props.class)"
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="grid place-content-center text-current transition-none"
    >
      <slot v-bind="slotProps">
        <Check class="size-3.5" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
