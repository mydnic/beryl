<template>
    <UButton
        :icon="isPlaying ? 'i-lucide-square' : 'i-lucide-play'"
        @click="togglePlay"
    />
</template>

<script>
export default {
    props: {
        music: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isPlaying: false,
            audioPlayer: null
        }
    },

    // Clean up when component is destroyed
    beforeUnmount() {
        this.stopMusic();
    },

    methods: {
        togglePlay() {
            if (this.isPlaying) {
                this.stopMusic();
            } else {
                // Emit event to parent to stop any other playing music
                this.$emit('play', this.music.id);
                this.playMusic();
            }
        },

        playMusic() {
            const streamUrl = `/music/${this.music.id}/stream`;
            this.audioPlayer = new Audio(streamUrl);
            
            // Set up event listener for when audio ends
            this.audioPlayer.addEventListener('ended', () => {
                this.isPlaying = false;
            });
            
            // Play the audio
            this.audioPlayer.play();
            this.isPlaying = true;
        },
        
        stopMusic() {
            if (this.audioPlayer) {
                this.audioPlayer.pause();
                this.audioPlayer.currentTime = 0;
                this.audioPlayer = null;
            }
            this.isPlaying = false;
        },

        // Public method that can be called from parent
        stop() {
            if (this.isPlaying) {
                this.stopMusic();
            }
        }
    }
}
</script>
