<template>
    <UApp>
        <div class="space-y-6">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <div class="flex max-w-7xl mx-auto items-center justify-between p-4">
                    <div class="">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            Beryl
                        </h1>
                    </div>
                    <ThemeSwitcher />
                </div>
            </div>
            <slot />
        </div>
    </UApp>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'

const page = usePage()
const toast = useToast()

// Watch for flash messages from Laravel
watch(
    () => page.props.flash,
    (flash) => {
        if (flash.success) {
            toast.add({
                title: 'Success',
                description: flash.success,
                color: 'green',
                icon: 'i-heroicons-check-circle',
                timeout: 5000
            })
        }

        if (flash.error) {
            toast.add({
                title: 'Error',
                description: flash.error,
                color: 'red',
                icon: 'i-heroicons-exclamation-circle',
                timeout: 5000
            })
        }
    },
    { immediate: true, deep: true }
)
</script>
