<?php
/**
 * src/VideoServiceFactory.php
 * Returns the active video generation service (Runway or Veo)
 * based on the 'video_provider' workflow setting.
 *
 * Usage:
 *   $svc = VideoServiceFactory::make();
 *   $job = $svc->queueVideoJob(...);
 */

class VideoServiceFactory
{
    /**
     * Returns RunwayVideoService or VeoVideoService based on the
     * 'video_provider' setting stored in workflow_settings.
     * Defaults to 'runway' if not set.
     */
    public static function make(): RunwayVideoService|VeoVideoService
    {
        $provider = strtolower(
            (string)($GLOBALS['appSettings']['video_provider'] ?? 'runway')
        );

        if ($provider === 'veo') {
            return new VeoVideoService();
        }

        return new RunwayVideoService();
    }

    /**
     * Returns the currently configured provider name: 'runway' or 'veo'
     */
    public static function activeProvider(): string
    {
        $provider = strtolower(
            (string)($GLOBALS['appSettings']['video_provider'] ?? 'runway')
        );
        return in_array($provider, ['runway', 'veo']) ? $provider : 'runway';
    }

    /**
     * Returns true if the active provider is in mock mode.
     */
    public static function isMockMode(): bool
    {
        if (self::activeProvider() === 'veo') {
            return defined('ENABLE_MOCK_VEO_MODE') && ENABLE_MOCK_VEO_MODE;
        }
        return defined('ENABLE_MOCK_RUNWAY_MODE') && ENABLE_MOCK_RUNWAY_MODE;
    }
}
