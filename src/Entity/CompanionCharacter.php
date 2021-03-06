<?php

namespace App\Entity;

use Ramsey\Uuid\Uuid;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(
 *     name="companion_characters",
 *     indexes={
 *          @ORM\Index(name="name", columns={"name"}),
 *          @ORM\Index(name="server", columns={"server"}),
 *          @ORM\Index(name="lodestone_id", columns={"lodestone_id"}),
 *          @ORM\Index(name="added", columns={"added"}),
 *          @ORM\Index(name="updated", columns={"updated"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\CompanionCharacterRepository")
 */
class CompanionCharacter
{
    const STATUS_NEW = 0;
    const STATUS_FOUND = 1;
    const STATUS_NOT_FOUND = 2;
    
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", length=64)
     */
    public $id;
    /**
     * @var string
     * @ORM\Column(type="string", length=64)
     */
    public $name;
    /**
     * @var int
     * @ORM\Column(type="integer", length=64)
     */
    public $server;
    /**
     * @var int
     * @ORM\Column(type="integer", length=16, nullable=true)
     */
    public $lodestoneId;
    /**
     * @var int
     * @ORM\Column(type="integer", length=16)
     */
    public $updated = 0;
    /**
     * @var int
     * @ORM\Column(type="integer", length=16)
     */
    public $added = 0;
    /**
     * @var int
     * @ORM\Column(type="integer", length=3, options={"default": 0})
     */
    public $status = 0;
    
    public function __construct(string $name, int $server)
    {
        $this->id       = Uuid::uuid4();
        $this->added    = time();
        $this->updated  = time();
        $this->name     = $name;
        $this->server   = $server;
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function setId(string $id)
    {
        $this->id = $id;
        
        return $this;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name)
    {
        $this->name = $name;
        
        return $this;
    }
    
    public function getServer(): int
    {
        return $this->server;
    }
    
    public function setServer(int $server)
    {
        $this->server = $server;
        
        return $this;
    }
    
    public function getLodestoneId(): int
    {
        return $this->lodestoneId;
    }
    
    public function setLodestoneId(int $lodestoneId)
    {
        $this->lodestoneId = $lodestoneId;
        
        return $this;
    }
    
    public function getUpdated(): int
    {
        return $this->updated;
    }
    
    public function setUpdated(int $updated)
    {
        $this->updated = $updated;
        
        return $this;
    }
    
    public function getAdded(): int
    {
        return $this->added;
    }
    
    public function setAdded(int $added)
    {
        $this->added = $added;
        
        return $this;
    }
    
    public function getStatus(): int
    {
        return $this->status;
    }
    
    public function setStatus(int $status)
    {
        $this->status = $status;
        
        return $this;
    }
}
