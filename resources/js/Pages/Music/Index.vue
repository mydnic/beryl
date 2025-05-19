<template>
    <div>
        <Link
            as="button"
            href="/scan"
            method="post"
        >
            Scan
        </Link>
    </div>
</template>

<script>
export default {
    props: {
        musics: {
            type: Array,
            default: []
        }
    }
}
</script>
