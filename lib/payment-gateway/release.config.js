module.exports = {
    "branches": ["main"],
    "plugins": [
        "@semantic-release/commit-analyzer",
        "@semantic-release/release-notes-generator",
        "@semantic-release/changelog",
        "@semantic-release/github",
        ["@semantic-release/git", {
            "assets": ["CHANGELOG.md"],
            "message": "chore(release): ${nextRelease.version} [skip ci]\n\n${nextRelease.notes}"
        }]
    ],
    "preset": "angular",
    "tagFormat": "${version}"
}