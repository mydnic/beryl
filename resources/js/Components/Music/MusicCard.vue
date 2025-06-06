<template>
    <UCard>
        <div class="flex font-semibold px-2 mb-2 text-xs">
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
        <div class="flex text-xs bg-slate-50 dark:bg-slate-800 px-2 mb-2 py-1 items-center">
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
            v-if="music.musicbrainz_data"
            class="mt-2 max-h-46 overflow-y-auto"
        >
            <div class="space-y-1 px-2 divide-slate-100 divide-y">
                <div
                    v-for="(result, index) in displayedResults"
                    :key="result.id"
                    class="flex font-semibold mb-2 pb-2 text-xs"
                >
                    <div class="flex-1 min-w-0">
                        <UBadge
                            v-if="result['artist-credit'] && result['artist-credit'].length"
                            :color="result['artist-credit'][0].name === music.artist ? 'success' : 'error'"
                            variant="subtle"
                        >
                            <span
                                v-for="(artist, index) in result['artist-credit']"
                                :key="index"
                            >
                                {{ artist.name }}{{ index < result['artist-credit'].length - 1 ? ', ' : '' }}
                            </span>
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
                            v-if="result.releases && result.releases.length"
                            variant="subtle"
                            :color="result.releases[0].title === music.album ? 'success' : 'error'"
                        >
                            {{ result.releases[0].title }}
                        </UBadge>
                    </div>
                    <div class="flex-1 min-w-0">
                        <UBadge
                            v-if="result['first-release-date']"
                            variant="subtle"
                            :color="result['first-release-date'].substring(0, 4) == music.release_year ? 'success' : 'error'"
                        >
                            {{ result['first-release-date'].substring(0, 4) }}
                        </UBadge>
                    </div>
                    <div class="w-20">
                        <UButton
                            icon="i-lucide-check-check"
                            color="neutral"
                            variant="ghost"
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
            // Construire les métadonnées à partir du résultat sélectionné
            const metadata = {
                title: result.title,
                artist: result['artist-credit'] ? result['artist-credit'].map(a => a.name).join(', ') : '',
                album: result.releases && result.releases.length ? result.releases[0].title : '',
                year: result['first-release-date'] ? result['first-release-date'].substring(0, 4) : ''
                // Ajouter d'autres métadonnées si nécessaire
            }

            // Envoyer les métadonnées au serveur pour mise à jour
            this.$inertia.post(`/music/${this.music.id}/apply-metadata`, { metadata, result })

            // Fermer le tableau de comparaison
            this.selectedResult = null
        }
    }
}
</script>
