<template>
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Beryl
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Manage and fix metadata for your music collection
                    </p>
                </div>
                <UButton
                    to="/scan"
                    method="post"
                    icon="i-lucide-scan-line"
                    size="lg"
                    color="primary"
                    variant="solid"
                >
                    Scan Library
                </UButton>
            </div>

            <!-- Search and Filter Controls -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex flex-col lg:flex-row gap-4">
                    <!-- Search Bar -->
                    <div class="flex-1">
                        <UInput
                            v-model="searchQuery"
                            icon="i-lucide-search"
                            placeholder="Search by title, artist, album, or file path..."
                            size="lg"
                            @input="debouncedSearch"
                        />
                    </div>

                    <!-- Sort Controls -->
                    <div class="flex gap-3">
                        <USelect
                            v-model="sortBy"
                            :items="sortOptions"
                            placeholder="Sort by"
                            size="lg"
                            class="w-40"
                            @change="updateSort"
                        />
                        <UButton
                            :icon="sortOrder === 'asc' ? 'i-lucide-arrow-up' : 'i-lucide-arrow-down'"
                            :color="sortOrder === 'asc' ? 'green' : 'blue'"
                            variant="outline"
                            size="lg"
                            @click="toggleSortOrder"
                        >
                            {{ sortOrder === 'asc' ? 'A-Z' : 'Z-A' }}
                        </UButton>
                    </div>
                </div>

                <!-- Active Filters Display -->
                <div
                    v-if="searchQuery || sortBy !== 'created_at'"
                    class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                >
                    <span class="text-sm text-gray-600 dark:text-gray-400">Active filters:</span>
                    <UBadge
                        v-if="searchQuery"
                        color="blue"
                        variant="soft"
                        class="gap-1"
                    >
                        <UIcon
                            name="i-lucide-search"
                            class="w-3 h-3"
                        />
                        "{{ searchQuery }}"
                        <UButton
                            icon="i-lucide-x"
                            size="2xs"
                            color="blue"
                            variant="ghost"
                            @click="clearSearch"
                        />
                    </UBadge>
                    <UBadge
                        v-if="sortBy !== 'created_at'"
                        color="green"
                        variant="soft"
                        class="gap-1"
                    >
                        <UIcon
                            name="i-lucide-arrow-up-down"
                            class="w-3 h-3"
                        />
                        {{ getSortLabel(sortBy) }} ({{ sortOrder }})
                        <UButton
                            icon="i-lucide-x"
                            size="2xs"
                            color="green"
                            variant="ghost"
                            @click="resetSort"
                        />
                    </UBadge>
                </div>
            </div>
        </div>

        <!-- Results Summary -->
        <div
            v-if="allMusics.length"
            class="mb-6"
        >
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Showing {{ allMusics.length }} of {{ pagination.total }} tracks
                    <span v-if="searchQuery">matching "{{ searchQuery }}"</span>
                </p>
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <UIcon
                        name="i-lucide-music"
                        class="w-4 h-4"
                    />
                    {{ pagination.total }} total tracks
                </div>
            </div>
        </div>

        <!-- Music Grid -->
        <div
            v-if="allMusics.length"
            class="space-y-3 mb-8"
        >
            <MusicCard
                v-for="music in allMusics"
                :key="music.id"
                :music="music"
                class="transition-all duration-200 hover:shadow-md"
            />
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="text-center py-16"
        >
            <div class="max-w-md mx-auto">
                <UIcon
                    name="i-lucide-music-off"
                    class="w-16 h-16 text-gray-400 mx-auto mb-4"
                />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    {{ searchQuery ? 'No tracks found' : 'No music in your library' }}
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ searchQuery
                        ? `No tracks match your search for "${searchQuery}". Try different keywords.`
                        : 'Start by scanning your music directory to populate your library.'
                    }}
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <UButton
                        v-if="searchQuery"
                        variant="outline"
                        @click="clearSearch"
                    >
                        Clear Search
                    </UButton>
                    <UButton
                        to="/scan"
                        method="post"
                        icon="i-lucide-scan-line"
                        color="primary"
                    >
                        {{ searchQuery ? 'Scan for More Music' : 'Scan Music Library' }}
                    </UButton>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div
            v-if="allMusics.length && pagination.total > pagination.per_page"
            class="flex justify-center py-8"
        >
            <UPagination
                :items-per-page="pagination.per_page"
                :total="pagination.total"
                :page="pagination.current_page"
                @update:page="handlePageChange"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

// Simple debounce function
const debounce = (func, wait) => {
    let timeout
    return function executedFunction (...args) {
        const later = () => {
            clearTimeout(timeout)
            func(...args)
        }
        clearTimeout(timeout)
        timeout = setTimeout(later, wait)
    }
}

const props = defineProps({
    musics: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_order: 'desc'
        })
    }
})

// Reactive data
const searchQuery = ref(props.filters.search)
const sortBy = ref(props.filters.sort_by)
const sortOrder = ref(props.filters.sort_order)

// Computed properties
const allMusics = computed(() => props.musics?.data || [])

const pagination = computed(() => ({
    per_page: props.musics.per_page,
    total: props.musics.total,
    current_page: props.musics.current_page
}))

const sortOptions = [
    { label: 'Artist Name', value: 'artist' },
    { label: 'Date Added', value: 'created_at' },
    { label: 'Release Year', value: 'release_year' }
]

// Methods
const getSortLabel = (value) => {
    const option = sortOptions.find(opt => opt.value === value)
    return option ? option.label : value
}

const buildQueryParams = () => {
    const params = new URLSearchParams()

    if (searchQuery.value) {
        params.set('search', searchQuery.value)
    }

    if (sortBy.value !== 'created_at') {
        params.set('sort_by', sortBy.value)
    }

    if (sortOrder.value !== 'desc') {
        params.set('sort_order', sortOrder.value)
    }

    return params.toString()
}

const updateUrl = () => {
    const queryString = buildQueryParams()
    const url = queryString ? `/?${queryString}` : '/'

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}

const debouncedSearch = debounce(() => {
    updateUrl()
}, 500)

const updateSort = () => {
    updateUrl()
}

const toggleSortOrder = () => {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
    updateUrl()
}

const clearSearch = () => {
    searchQuery.value = ''
    updateUrl()
}

const resetSort = () => {
    sortBy.value = 'created_at'
    sortOrder.value = 'desc'
    updateUrl()
}

const handlePageChange = (page) => {
    const queryString = buildQueryParams()
    const baseUrl = queryString ? `/?${queryString}` : '/'
    const url = `${baseUrl}${queryString ? '&' : '?'}page=${page}`

    router.visit(url, {
        preserveState: true,
        preserveScroll: false
    })
}

// Watch for prop changes to sync reactive data
watch(() => props.filters, (newFilters) => {
    searchQuery.value = newFilters.search
    sortBy.value = newFilters.sort_by
    sortOrder.value = newFilters.sort_order
}, { immediate: true })
</script>
