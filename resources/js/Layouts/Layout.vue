<template>
    <UApp>
        <div class="space-y-6 p-4">
            <div class="flex items-center justify-between">
                <div class="">
                    <img
                        src=""
                        alt=""
                    >
                </div>
                <ThemeSwitcher />
            </div>
            <slot />
        </div>
    </UApp>
</template>

<script>
export default {
    name: 'Layout'
}
</script>
