<template>
    <UCard>
        <div class="flex gap-2 font-semibold px-2 mb-2 text-xs">
            <div class="flex-1 min-w-0">
                Artist
            </div>
            <div class="flex-1 min-w-0">
                Title
            </div>
            <div class="flex-1 min-w-0">
                Album
            </div>
            <div class="flex-1 min-w-0">
                Release
            </div>
            <div class="w-20" />
        </div>
        <div class="flex gap-2 text-xs bg-slate-50 dark:bg-slate-800 px-2 mb-2 py-1 items-center">
            <div class="flex-1 min-w-0">
                <UBadge
                    color="success"
                >
                    {{ music.artist }}
                </UBadge>
            </div>
            <div class="flex-1 min-w-0">
                <UBadge
                    color="success"
                >
                    {{ music.title }}
                </UBadge>
            </div>
            <div class="flex-1 min-w-0">
                <UBadge
                    color="success"
                >
                    {{ music.album }}
                </UBadge>
            </div>
            <div class="flex-1 min-w-0">
                <UBadge
                    color="success"
                >
                    {{ music.release_year }}
                </UBadge>
            </div>
            <div class="w-20 flex justify-end">
                <UDropdownMenu
                    :items="[
                        [
                            {
                                label: 'Search Metadata',
                                icon: 'i-lucide-search',
                                onSelect: () => {
                                    $inertia.post(`/music/${music.id}/metadata`)
                                }
                            },
                            {
                                label: 'Delete',
                                icon: 'i-lucide-trash',
                                color: 'error',
                                onSelect: deleteMusic(music)
                            }
                        ]
                    ]"
                >
                    <UButton
                        icon="i-lucide-ellipsis-vertical"
                        color="neutral"
                        variant="ghost"
                    />
                </UDropdownMenu>
            </div>
        </div>
        <div
            v-if="music.results"
            class="mt-2 max-h-46 overflow-y-auto"
        >
            <div class="space-y-1 px-2 divide-slate-100 divide-y">
                <div
                    v-for="(result, index) in music.results"
                    :key="result.id"
                    class="flex gap-2 font-semibold mb-2 pb-2 text-xs"
                >
                    <div class="flex-1 min-w-0">
                        <UBadge
                            v-if="result.artist"
                            :color="result.artist === music.artist ? 'success' : 'error'"
                            variant="subtle"
                        >
                            {{ result.artist }}
                        </UBadge>
                    </div>
                    <div class="flex-1 min-w-0">
                        <UBadge
                            :color="result.title === music.title ? 'success' : 'error'"
                            variant="subtle"
                        >
                            {{ result.title }}
                        </UBadge>
                    </div>
                    <div class="flex-1 min-w-0">
                        <UBadge
                            v-if="result.album"
                            variant="subtle"
                            :color="result.album === music.album ? 'success' : 'error'"
                        >
                            {{ result.album }}
                        </UBadge>
                    </div>
                    <div class="flex-1 min-w-0">
                        <UBadge
                            v-if="result.release_year"
                            variant="subtle"
                            :color="result.release_year == music.release_year ? 'success' : 'error'"
                        >
                            {{ result.release_year }}
                        </UBadge>
                    </div>
                    <div class="w-20">
                        <UButton
                            icon="i-lucide-check-check"
                            color="neutral"
                            variant="ghost"
                            :loading="loading"
                            size="sm"
                            @click="applyMetadata(result)"
                        />
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-between mt-2">
            <MusicPlayButton
                :music="music"
                size="sm"
            />
            <span class="text-xs text-gray-500">{{ music.filepath }}</span>
        </div>
    </UCard>
</template>

<script>
export default {
    name: 'MusicCard',

    props: {
        music: {
            type: Object,
            required: true
        }
    },

    data () {
        return {
            loading: false,
            showAllResults: true,
            selectedResult: null
        }
    },

    computed: {
        displayedResults () {
            if (!this.music.musicbrainz_data || !this.music.musicbrainz_data.results) {
                return []
            }

            if (this.showAllResults) {
                return this.music.musicbrainz_data.results
            } else {
                return this.music.musicbrainz_data.results.slice(0, 3)
            }
        }
    },

    methods: {
        deleteMusic (music) {
            return () => {
                this.$inertia.delete(`/music/${music.id}`)
            }
        },

        applyMetadata (result) {
            this.loading = true
            // Construire les métadonnées à partir du résultat sélectionné
            const metadata = {
                title: result.title,
                artist: result.artist,
                album: result.album,
                year: result.release_year || this.music.release_year
                // Ajouter d'autres métadonnées si nécessaire
            }

            // Envoyer les métadonnées au serveur pour mise à jour
            this.$inertia.post(`/music/${this.music.id}/apply-metadata`,
                { metadata, result },
                {
                    preserveScroll: true,
                    onFinish: () => {
                        this.loading = false
                    }
                }
            )

            // Fermer le tableau de comparaison
            this.selectedResult = null
        }
    }
}
</script>
