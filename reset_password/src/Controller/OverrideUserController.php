<?php

namespace Drupal\reset_password\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\user\Controller\UserController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for user routes.
 */
class OverrideUserController extends UserController {

  /**
   * Validates user, hash, and timestamp; logs the user in if correct.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If $uid is for a blocked user or invalid user ID.
   *
   * @noinspection DuplicatedCode
   */
  public function resetPassLogin($uid, $timestamp, $hash): RedirectResponse {
    // The current user is not logged in, so check the parameters.
    $current = REQUEST_TIME;
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($uid);

    // Verify that the user exists and is active.
    if ($user === NULL || !$user->isActive()) {
      // Blocked or invalid user ID, so deny access. The parameters will be in
      // the watchdog's URL for the administrator to check.
      throw new AccessDeniedHttpException();
    }

    // Time out, in seconds, until login URL expires.
    $timeout = $this->config('user.settings')->get('password_reset_timeout');
    // No time out for first time login.
    if ($current - $timestamp > $timeout && $user->getLastLoginTime()) {
      $this->messenger()
        ->addError($this->t('You have tried to use a one-time login link that has expired. Please request a new one using the form below.'));

      return $this->redirect('user.pass');
    }

    if (
      $timestamp <= $current
      && $user->isAuthenticated()
      && hash_equals($hash, reset_password_user_pass_rehash($user, $timestamp))
    ) {
      user_login_finalize($user);
      $this->logger->notice('User %name used one-time login link at time %timestamp.', [
        '%name' => $user->getDisplayName(),
        '%timestamp' => $timestamp
      ]);
      $this->messenger()
        ->addStatus($this->t('You have just used your one-time login link. It is no longer necessary to use this link to log in. Please change your password.'));
      // Let the user's password be changed without the current password
      // check.
      $token = Crypt::randomBytesBase64(55);
      $_SESSION['pass_reset_' . $user->id()] = $token;
      // Clear any flood events for this user.
      $this->flood->clear('user.password_request_user', $uid);

      return $this->redirect(
        'entity.user.edit_form',
        ['user' => $user->id()],
        [
          'query' => ['pass-reset-token' => $token],
          'absolute' => TRUE,
        ]
      );
    }

    $this->messenger()
      ->addError($this->t('You have tried to use a one-time login link that has either been used or is no longer valid. Please request a new one using the form below.'));

    return $this->redirect('user.pass');
  }

}
