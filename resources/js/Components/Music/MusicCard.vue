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
        <div class="flex text-xs bg-slate-50 px-2 mb-2 py-1 items-center">
            <div class="flex-1 min-w-0">
                <span class="bg-green-200 border-b-green-700 text-slate-800 py-1 px-2 border border-dotted">{{ music.artist }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <span class="bg-green-200 border-b-green-700 text-slate-800 py-1 px-2 border border-dotted">{{ music.title }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <span class="bg-green-200 border-b-green-700 text-slate-800 py-1 px-2 border border-dotted">{{ music.album }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <span class="bg-green-200 border-b-green-700 text-slate-800 py-1 px-2 border border-dotted">{{ music.release_year }}</span>
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
                        <template v-if="result['artist-credit'] && result['artist-credit'].length">
                            <span
                                v-for="(artist, index) in result['artist-credit']"
                                :key="index"
                            >
                                {{ artist.name }}{{ index < result['artist-credit'].length - 1 ? ', ' : '' }}
                            </span>
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        {{ result.title }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <template v-if="result.releases && result.releases.length">
                            {{ result.releases[0].title }}
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <template v-if="result['first-release-date']">
                            ({{ result['first-release-date'].substring(0, 4) }})
                        </template>
                    </div>
                    <div class="w-20" />
                </div>

                <!-- Tableau comparatif des métadonnées -->
                <UCard
                    v-if="selectedResult"
                    class="mt-2 p-2 bg-gray-50"
                >
                    <div class="text-xs font-medium mb-1 flex justify-between items-center">
                        <span>Comparaison des métadonnées</span>
                        <UButton
                            icon="i-lucide-x"
                            color="gray"
                            variant="ghost"
                            size="xs"
                            @click="selectedResult = null"
                        />
                    </div>
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="py-1 px-2 text-left font-medium">
                                    Tag
                                </th>
                                <th class="py-1 px-2 text-left font-medium">
                                    Actuel
                                </th>
                                <th class="py-1 px-2 text-left font-medium">
                                    Nouveau
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b border-gray-200"
                                :class="{ 'bg-yellow-50': music.title !== getNewMetadata().title }"
                            >
                                <td class="py-1 px-2 font-medium">
                                    Titre
                                </td>
                                <td class="py-1 px-2">
                                    {{ music.title || '-' }}
                                </td>
                                <td class="py-1 px-2">
                                    {{ getNewMetadata().title || '-' }}
                                </td>
                            </tr>
                            <tr
                                class="border-b border-gray-200"
                                :class="{ 'bg-yellow-50': music.artist !== getNewMetadata().artist }"
                            >
                                <td class="py-1 px-2 font-medium">
                                    Artiste
                                </td>
                                <td class="py-1 px-2">
                                    {{ music.artist || '-' }}
                                </td>
                                <td class="py-1 px-2">
                                    {{ getNewMetadata().artist || '-' }}
                                </td>
                            </tr>
                            <tr
                                class="border-b border-gray-200"
                                :class="{ 'bg-yellow-50': music.album !== getNewMetadata().album }"
                            >
                                <td class="py-1 px-2 font-medium">
                                    Album
                                </td>
                                <td class="py-1 px-2">
                                    {{ music.album || '-' }}
                                </td>
                                <td class="py-1 px-2">
                                    {{ getNewMetadata().album || '-' }}
                                </td>
                            </tr>
                            <tr :class="{ 'bg-yellow-50': music.year !== getNewMetadata().year }">
                                <td class="py-1 px-2 font-medium">
                                    Année
                                </td>
                                <td class="py-1 px-2">
                                    {{ music.year || '-' }}
                                </td>
                                <td class="py-1 px-2">
                                    {{ getNewMetadata().year || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-2 flex justify-end">
                        <UButton
                            size="xs"
                            color="primary"
                            @click="applyMetadata(selectedResult)"
                        >
                            Appliquer ces métadonnées
                        </UButton>
                    </div>
                </UCard>
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

        selectResult (result) {
            this.applyMetadata(result)
        },

        toggleResultDetails (result) {
            if (this.selectedResult && this.selectedResult.id === result.id) {
                this.selectedResult = null
            } else {
                this.selectedResult = result
            }
        },

        getNewMetadata () {
            if (!this.selectedResult) return {}

            return {
                title: this.selectedResult.title || '',
                artist: this.selectedResult['artist-credit'] ? this.selectedResult['artist-credit'].map(a => a.name).join(', ') : '',
                album: this.selectedResult.releases && this.selectedResult.releases.length ? this.selectedResult.releases[0].title : '',
                year: this.selectedResult['first-release-date'] ? this.selectedResult['first-release-date'].substring(0, 4) : ''
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
        },

        formatDuration (ms) {
            if (!ms) return ''
            const totalSeconds = Math.floor(ms / 1000)
            const minutes = Math.floor(totalSeconds / 60)
            const seconds = totalSeconds % 60
            return `${minutes}:${seconds.toString().padStart(2, '0')}`
        }
    }
}
</script>
