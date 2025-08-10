<template>
    <div v-if="jobStats.is_processing || jobStats.total_failed > 0" class="mb-6">
        <UCard>
            <template #header>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <UIcon 
                            :name="jobStats.is_processing ? 'i-lucide-loader-2' : 'i-lucide-check-circle'"
                            :class="jobStats.is_processing ? 'animate-spin text-blue-500' : 'text-green-500'"
                            class="w-5 h-5"
                        />
                        <h3 class="text-lg font-semibold">
                            {{ jobStats.is_processing ? 'Processing Jobs' : 'Jobs Completed' }}
                        </h3>
                    </div>
                    <UButton
                        icon="i-lucide-refresh-cw"
                        variant="ghost"
                        size="sm"
                        @click="refreshStats"
                        :loading="refreshing"
                    />
                </div>
            </template>

            <!-- Progress Overview -->
            <div class="space-y-4">
                <!-- Overall Status -->
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <UIcon name="i-lucide-clock" class="w-4 h-4 text-blue-500" />
                            <span class="text-sm font-medium">Pending Jobs:</span>
                            <UBadge :color="jobStats.total_pending > 0 ? 'blue' : 'gray'">
                                {{ jobStats.total_pending }}
                            </UBadge>
                        </div>
                        <div v-if="jobStats.total_failed > 0" class="flex items-center gap-2">
                            <UIcon name="i-lucide-alert-circle" class="w-4 h-4 text-red-500" />
                            <span class="text-sm font-medium">Failed:</span>
                            <UBadge color="red">{{ jobStats.total_failed }}</UBadge>
                        </div>
                    </div>
                    <div v-if="jobStats.is_processing" class="text-sm text-gray-600 dark:text-gray-400">
                        Processing...
                    </div>
                </div>

                <!-- Job Types Breakdown -->
                <div v-if="Object.keys(jobStats.jobs_by_type).length > 0" class="space-y-3">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Job Types:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div 
                            v-for="(count, type) in jobStats.jobs_by_type" 
                            :key="type"
                            class="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600"
                        >
                            <div class="flex items-center gap-2">
                                <UIcon 
                                    :name="getJobTypeIcon(type)" 
                                    class="w-4 h-4"
                                    :class="getJobTypeColor(type)"
                                />
                                <span class="text-sm">{{ type }}</span>
                            </div>
                            <UBadge :color="getJobTypeBadgeColor(type)">{{ count }}</UBadge>
                        </div>
                    </div>
                </div>

                <!-- Recent Failed Jobs -->
                <div v-if="jobStats.recent_failed && jobStats.recent_failed.length > 0" class="space-y-3">
                    <h4 class="text-sm font-medium text-red-700 dark:text-red-300">Recent Failures:</h4>
                    <div class="space-y-2">
                        <div 
                            v-for="(failure, index) in jobStats.recent_failed" 
                            :key="failure.id ?? index"
                            class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-red-800 dark:text-red-200">
                                        {{ failure.job_class }}
                                    </div>
                                    <div class="text-xs text-red-600 dark:text-red-400 mt-1">
                                        {{ failure.error }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 ml-2">
                                    <div class="text-xs text-red-500 dark:text-red-400">
                                        {{ formatDate(failure.failed_at) }}
                                    </div>
                                    <UButton
                                        icon="i-lucide-trash-2"
                                        size="xs"
                                        color="red"
                                        variant="ghost"
                                        :loading="deletingIds.has(failure.id)"
                                        @click="deleteFailedJob(failure)"
                                        :title="'Delete failed job'"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Auto-refresh indicator -->
                <div v-if="jobStats.is_processing" class="flex items-center justify-center pt-2">
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <UIcon name="i-lucide-refresh-cw" class="w-3 h-3 animate-spin" />
                        Auto-refreshing every {{ refreshInterval / 1000 }}s
                    </div>
                </div>
            </div>
        </UCard>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    initialStats: {
        type: Object,
        required: true
    }
})

const jobStats = ref(props.initialStats)
const refreshing = ref(false)
const refreshInterval = 5000 // 5 seconds
let intervalId = null
const deletingIds = new Set()

// Auto-refresh when jobs are processing
const startAutoRefresh = () => {
    if (intervalId) clearInterval(intervalId)
    
    if (jobStats.value.is_processing) {
        intervalId = setInterval(() => {
            refreshStats()
        }, refreshInterval)
    }
}

const refreshStats = async () => {
    refreshing.value = true
    try {
        const response = await fetch('/jobs/stats')
        const data = await response.json()
        jobStats.value = data
        
        // Restart auto-refresh based on new status
        startAutoRefresh()
    } catch (error) {
        console.error('Failed to refresh job stats:', error)
    } finally {
        refreshing.value = false
    }
}

const getCsrfToken = () => {
    const el = document.querySelector('meta[name="csrf-token"]')
    return el ? el.getAttribute('content') : undefined
}

const deleteFailedJob = async (failure) => {
    if (!failure?.id) return
    const confirmed = window.confirm('Remove this failed job from the list?')
    if (!confirmed) return
    deletingIds.add(failure.id)
    try {
        const res = await fetch(`/jobs/failed/${failure.id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken() || ''
            }
        })
        if (!res.ok) throw new Error('Failed to delete failed job')
        // Optimistically remove from local state
        jobStats.value.recent_failed = jobStats.value.recent_failed.filter(j => j.id !== failure.id)
        // Also refresh counts
        await refreshStats()
    } catch (e) {
        console.error(e)
        alert('Could not delete failed job.')
    } finally {
        deletingIds.delete(failure.id)
    }
}

const getJobTypeIcon = (type) => {
    const icons = {
        'Directory Scanning': 'i-lucide-folder-search',
        'Processing Files': 'i-lucide-file-audio',
        'Fetching Metadata': 'i-lucide-download',
        'Other': 'i-lucide-cog'
    }
    return icons[type] || 'i-lucide-cog'
}

const getJobTypeColor = (type) => {
    const colors = {
        'Directory Scanning': 'text-purple-500',
        'Processing Files': 'text-blue-500',
        'Fetching Metadata': 'text-green-500',
        'Other': 'text-gray-500'
    }
    return colors[type] || 'text-gray-500'
}

const getJobTypeBadgeColor = (type) => {
    const colors = {
        'Directory Scanning': 'purple',
        'Processing Files': 'blue',
        'Fetching Metadata': 'green',
        'Other': 'gray'
    }
    return colors[type] || 'gray'
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString()
}

onMounted(() => {
    startAutoRefresh()
})

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId)
    }
})

// Watch for changes in processing status
const isProcessing = computed(() => jobStats.value.is_processing)
</script>
