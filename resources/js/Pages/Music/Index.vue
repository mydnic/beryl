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

        <div>
            <UTable
                :data="musics"
                :columns="[
                    { accessorKey: 'id', header: 'Id' },
                    { accessorKey: 'relative_path', header: 'Filepath' },
                    { accessorKey: 'artist', header: 'Artist' },
                    { accessorKey: 'album', header: 'Album' },
                    { accessorKey: 'title', header: 'Title' },
                    { accessorKey: 'release_year', header: 'Year' },
                    { accessorKey: 'genre', header: 'Genre' },
                    { accessorKey: 'actions', header: '' }
                ]"
                class="flex-1"
            >
                <template #actions-cell="{ row }">
                    <UDropdownMenu
                        :items="[
                            [
                                {
                                    label: 'Delete',
                                    icon: 'i-lucide-trash',
                                    color: 'error',
                                    onSelect: deleteMusic(row.original)
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
                </template>
            </utable>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        musics: {
            type: Array,
            default: () => []
        }
    },

    methods: {
        deleteMusic (music) {
            return () => {
                this.$inertia.delete(`/music/${music.id}`)
            }
        }
    }
}
</script>
