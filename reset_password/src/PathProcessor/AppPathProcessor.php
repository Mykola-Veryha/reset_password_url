<?php

namespace Drupal\reset_password\PathProcessor;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes the inbound path.
 */
class AppPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * The entity storage for users.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $userStorage;

  /**
   * Create AppPathProcessor.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->userStorage = $entity_type_manager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    return $path;
  }

  /**
   * {@inheritdoc}
   *
   * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
   */
  public function processOutbound(
    $path,
    &$options = [],
    Request $request = NULL,
    BubbleableMetadata $bubbleable_metadata = NULL
  ): string {
    if (
      isset($options['route'])
      && $options['route'] instanceof Route
      && $options['route']->compile() !== NULL
    ) {
      preg_match($options['route']->compile()->getRegex(), $path, $matches);
      if ($options['route']->getPath() === '/user/reset/{uid}/{timestamp}/{hash}/login') {
        return $this->updateResetLoginPath($path, $matches);
      }
      if ($options['route']->getPath() === '/user/reset/{uid}/{timestamp}/{hash}') {
        return $this->updateResetPath($path, $matches);
      }
    }
    return $path;
  }

  /**
   * Update a path to user.reset route.
   */
  private function updateResetPath($path, $matches): string {
    $timestamp = $matches['timestamp'] ?? '';
    $uid = $matches['uid'] ?? '';
    $user = $this->userStorage->load($uid);
    if ($user instanceof UserInterface) {
      return sprintf(
        '/user/reset/%s/%s/%s',
        $uid,
        $timestamp,
        reset_password_user_pass_rehash($user, $timestamp),
      );
    }

    return $path;
  }

  /**
   * Update a path to user.reset.login route.
   */
  private function updateResetLoginPath($path, $matches): string {
    $timestamp = $matches['timestamp'] ?? '';
    $uid = $matches['uid'] ?? '';
    $user = $this->userStorage->load($uid);
    if ($user instanceof UserInterface) {
      return sprintf(
        '/user/reset/%s/%s/%s/login',
        $uid,
        $timestamp,
        reset_password_user_pass_rehash($user, $timestamp),
      );
    }

    return $path;
  }

}
