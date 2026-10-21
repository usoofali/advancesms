<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';

withDefaults(
    defineProps<{
        variant?: 'ghost' | 'outline' | 'secondary';
        size?: 'sm' | 'default' | 'icon';
    }>(),
    {
        variant: 'ghost',
        size: 'icon',
    },
);

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    :variant="variant"
                    :size="size"
                    @click="toggleTheme"
                    class="size-8 rounded-md text-muted-foreground hover:text-foreground transition-all duration-200"
                    aria-label="Toggle dark/light theme"
                >
                    <Sun
                        v-if="resolvedAppearance === 'dark'"
                        class="size-4 text-amber-400 rotate-0 scale-100 transition-all duration-300 hover:rotate-45"
                    />
                    <Moon
                        v-else
                        class="size-4 text-slate-700 dark:text-slate-200 rotate-0 scale-100 transition-all duration-300 hover:-rotate-12"
                    />
                </Button>
            </TooltipTrigger>
            <TooltipContent side="bottom" align="center" class="text-xs">
                Switch to {{ resolvedAppearance === 'dark' ? 'Light' : 'Dark' }} mode
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
