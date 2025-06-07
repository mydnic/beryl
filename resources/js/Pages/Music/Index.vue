<template>
    <div>
        <div class="text-center">
            <UButton
                to="/scan"
                method="post"
            >
                Scan
            </UButton>
        </div>

        <div class="space-y-4">
            <MusicCard
                v-for="music in musics"
                :key="music.id"
                :music="music"
            />
        </div>
    </div>
</template>

<script setup>
import { usePoll } from '@inertiajs/vue3'

defineProps({
    musics: {
        type: Array,
        default: () => []
    }
})

usePoll(5000)
</script>
