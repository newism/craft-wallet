<?php

namespace newism\wallet\models;

use SplFileInfo;

/**
 * Value object representing the result of adding a pass to a wallet platform.
 *
 * Use the static factory methods to create instances:
 * - AddToWalletResult::forApple($pkPass) — for Apple .pkpass file downloads
 * - AddToWalletResult::forGoogle($url) — for Google Wallet save URL redirects
 */
class AddToWalletResult
{
    private function __construct(
        public readonly string       $platform,
        public readonly ?SplFileInfo $pkPass = null,
        public readonly ?string      $redirectUrl = null,
        public readonly ?string      $contentType = null,
    ) {
    }

    public static function forApple(SplFileInfo $pkPass): self
    {
        return new self(
            platform: 'apple',
            pkPass: $pkPass,
            contentType: 'application/vnd.apple.pkpass',
        );
    }

    public static function forGoogle(string $redirectUrl): self
    {
        return new self(
            platform: 'google',
            redirectUrl: $redirectUrl,
        );
    }

    public function isDownload(): bool
    {
        return $this->pkPass !== null;
    }

    public function isRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }
}
