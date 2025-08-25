<template>
    <UApp>
        <div class="space-y-6">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <div class="flex max-w-7xl mx-auto items-center justify-between p-4">
                    <div class="flex items-center gap-2">
                        <img
                            :src="'/beryl-logo.svg'"
                            alt="Beryl"
                            width="48"
                            height="48"
                        >
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            Beryl
                        </h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <UButton
                            icon="i-heroicons-cog-6-tooth"
                            color="gray"
                            variant="soft"
                            @click="openSettings"
                        >
                            Settings
                        </UButton>
                        <ThemeSwitcher />
                    </div>
                </div>
            </div>

            <!-- Settings Modal -->
            <UModal v-model:open="isSettingsOpen">
                <template #content>
                    <UCard>
                        <template #header>
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold">
                                    Settings
                                </h3>
                                <UButton
                                    color="gray"
                                    variant="ghost"
                                    icon="i-heroicons-x-mark"
                                    @click="isSettingsOpen=false"
                                />
                            </div>
                        </template>
                        <SettingsForm
                            :initial="{ rename_on_apply: !!(page.props.settings?.rename_on_apply) }"
                            @saved="onSettingsSaved"
                            @cancel="isSettingsOpen=false"
                        />
                    </UCard>
                </template>
            </UModal>
            <slot />
        </div>
    </UApp>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { watch, ref } from 'vue'
import SettingsForm from '@/Components/Settings/SettingsForm.vue'

const page = usePage()
const toast = useToast()

const isSettingsOpen = ref(false)

function openSettings () {
    isSettingsOpen.value = true
}

function onSettingsSaved () {
    toast.add({ title: 'Saved', description: 'Settings updated', color: 'green', icon: 'i-heroicons-check-circle' })
    isSettingsOpen.value = false
}

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
