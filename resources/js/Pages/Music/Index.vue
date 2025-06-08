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
import { useEchoPublic } from '@laravel/echo-vue'

defineProps({
    musics: {
        type: Array,
        default: () => []
    }
})

useEchoPublic(
    `music-added`,
    'ProcessMusicFileJob',
    (e) => {
        console.log('Événement reçu:', e)
    }
)
</script>
