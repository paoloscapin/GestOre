chrome.webRequest.onBeforeSendHeaders.addListener(
    (details) => {
        const headers = Array.isArray(details.requestHeaders) ? details.requestHeaders : [];
        const authHeader = headers.find((header) => {
            return header && header.name && header.name.toLowerCase() === 'authorization';
        });

        if (!authHeader || !authHeader.value || !authHeader.value.startsWith('Bearer ')) {
            return;
        }

        chrome.storage.session.set({
            isirelBearerToken: authHeader.value,
            isirelBearerTokenSeenAt: new Date().toISOString(),
            isirelBearerTokenSourceUrl: details.url
        });
    },
    {
        urls: [
            'https://istruzione.cloud.provincia.tn.it/services/*'
        ]
    },
    [
        'requestHeaders',
        'extraHeaders'
    ]
);
