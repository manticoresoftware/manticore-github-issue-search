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
			$Mailer->isHTML(true);
			$project = $repo->org . '/' . $repo->name;
			$ImageRes = static::getGithubOpengraphImage($repo);
			$og_image_html = '';
			if (!$ImageRes->err) {
				$image_url = result($ImageRes);
				$og_image_html = "<a href="{$host}/{$project}"><img src='{$image_url}' alt='{$project}'/></a><br/>";
			}
			$Mailer->Subject = "github.manticoresearch.com/{$project} is ready";
			$Mailer->Body = "Hey there
<br/><br/>
Your repo <a href='https://github.com/{$project}'>{$project}</a> is now searchable at <a href='{$host}/{$project}'>{$host}</a>.<br/>
{$og_image_html}
Interested in the magic behind it? Remember, <a href='{$host}'>{$host}</a> is a demo of <a href='https://manticoresearch.com'>Manticore Search</a>.
<br/>
The code is open for you to explore and contribute. Find it <a href='https://github.com/manticoresoftware/manticore-github-issue-search'>here</a>.
<br/><br/>
Best wishes,<br/>
Manticore team";
			$Mailer->AltBody = "Hey there

Your repo https://github.com/{$project} is now searchable at {$host}/{$project} .

Interested in the magic behind it? Remember, https://github.manticoresearch.com/ is a demo of Manticore Search.

The code is open for you to explore and contribute. Find it here https://github.com/manticoresoftware/manticore-github-issue-search .

Best wishes,
Manticore team";
			$Mailer->setFrom($gmail_account);
			$Mailer->addAddress($email);
			$Mailer->send();
		}
		return ok(true);
	}

	/**
	 * Get the image URL for opengraph of request repository
	 * @param  Repo   $repo
	 * @return Result<string>
	 */
	public static function getGithubOpengraphImage(Repo $repo): Result {
		$url = "https://github.com/{$repo->org}/{$repo->name}";
		$html = @file_get_contents($url) ?: '';

		preg_match('/property="og:image" content="(.*?)"/ius', $html, $matches);
		if (isset($matches[1])) {
			return ok($matches[1]);
		}

		return err('e_opegraph_not_found');
	}
}
