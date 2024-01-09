<?php declare(strict_types=1);

namespace App\Component;

use App\Lib\Manticore;
use App\Model\Repo;
use Cli;
use PHPMailer\PHPMailer\PHPMailer;
use Result;

final class Notification {

	/**
	 * Subscribe the email to the requested repo id to get message when it's done
	 * @param Repo $repo
	 * @param  string $email
	 * @return Result<bool>
	 */
	public static function subscribe(Repo $repo, string $email): Result {
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return err('e_email_invalid');
		}
		return Manticore::addRepoSubscriber($repo->id, $email);
	}

	/**
	 * Notify all subscribers to the repo id
	 * @param Repo $repo
	 * @return Result<bool>
	 */
	public static function notify(Repo $repo): Result {
		$gmail_account = getenv('GMAIL_ACCOUNT');
		$gmail_password = getenv('GMAIL_PASSWORD');
		$emails = Manticore::getRepoSubscribers($repo->id);
		$host = config('common.proto') . '://' . config('common.domain');
		foreach ($emails as $email) {
			Cli::print(" {$email}");
			$Mailer = new PHPMailer;
			$Mailer->isSMTP();
			$Mailer->Host = 'smtp.gmail.com';
			$Mailer->SMTPAuth = true;
			$Mailer->Username = $gmail_account;
			$Mailer->Password = $gmail_password;
			$Mailer->SMTPSecure = 'tls';
			$Mailer->Port = 587;
			$Mailer->isHTML(false);
			$Mailer->Subject = "✅ Issues indexed for {$repo->org}/{$repo->name} with Manticore";
			$Mailer->Body = "👋Hello,

We are pleased to inform you that the GitHub Issues for {$repo->org}/{$repo->name} can now be searched here: {$host}/{$repo->org}/{$repo->name}.

Best regards,
The Manticore Team";
			$Mailer->setFrom($gmail_account);
			$Mailer->addAddress($email);
			$Mailer->send();
		}
		return ok(true);
	}

}
