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
                v-for="music in allMusics"
                :key="music.id"
                :music="music"
            />
        </div>

        <div
            v-if="allMusics.length"
            class="flex py-10 justify-center"
        >
            <UPagination
                :items-per-page="pagination.per_page"
                :total="pagination.total"
                :page="pagination.current_page"
                @update:page="(page) => $inertia.visit(`?page=${page}`)"
            />
        </div>
    </div>
</template>

<script setup>
import { useEchoPublic } from '@laravel/echo-vue'

const props = defineProps({
    musics: {
        type: Object,
        required: true
    }
})

const allMusics = props.musics?.data

const pagination = {
    per_page: props.musics.per_page,
    total: props.musics.total,
    current_page: props.musics.current_page
}

useEchoPublic(
    `music-created`,
    'MusicCreatedEvent',
    (e) => {
        allMusics.push(e.music)
    }
)
</script>
