<?php

namespace Shoper\Sdk\Rest\NewsComments\Requests;

use Shoper\Sdk\Rest\Core\Json\JsonSerializableType;
use Shoper\Sdk\Rest\Core\Json\JsonProperty;

class NewsCommentInsert extends JsonSerializableType
{
    /**
     * comment content. **Required.** Leading and trailing whitespace is trimmed and the result must be between
     * 1 and 5120 characters, so a blank string is rejected.
     *
     * @var string $content
     */
    #[JsonProperty('content')]
    public string $content;

    /**
     * @var ?string $date creation date(format: YYYY-MM-dd HH:mm:ss)
     */
    #[JsonProperty('date')]
    public ?string $date;

    /**
     * @var int $langId [language](#tag/Languages) identifier of the comment. **Required** and it must point to an existing locale.
     */
    #[JsonProperty('lang_id')]
    public int $langId;

    /**
     * [news](#tag/News) identifier the comment is attached to. **Required** and it must point to an existing
     * blog post.
     *
     * @var int $newsId
     */
    #[JsonProperty('news_id')]
    public int $newsId;

    /**
     * author [user](#tag/Users) identifier. Optional - when omitted the comment is treated as anonymous and
     * `user_name` becomes required. When supplied it must point to an existing customer account.
     *
     * @var ?int $userId
     */
    #[JsonProperty('user_id')]
    public ?int $userId;

    /**
     * author user name, up to 100 characters. **Required when `user_id` is not supplied** (or is empty);
     * otherwise optional.
     *
     * @var ?string $userName
     */
    #[JsonProperty('user_name')]
    public ?string $userName;

    /**
     * @var ?bool $validated is comment accepted by admin?
     */
    #[JsonProperty('validated')]
    public ?bool $validated;

    /**
     * @param array{
     *   content: string,
     *   langId: int,
     *   newsId: int,
     *   date?: ?string,
     *   userId?: ?int,
     *   userName?: ?string,
     *   validated?: ?bool,
     * } $values
     */
    public function __construct(
        array $values,
    ) {
        $this->content = $values['content'];
        $this->date = $values['date'] ?? null;
        $this->langId = $values['langId'];
        $this->newsId = $values['newsId'];
        $this->userId = $values['userId'] ?? null;
        $this->userName = $values['userName'] ?? null;
        $this->validated = $values['validated'] ?? null;
    }
}
