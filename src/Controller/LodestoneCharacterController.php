<?php

namespace App\Controller;

use App\Common\Constants\LodestoneConstants;
use App\Exception\ContentGoneException;
use App\Service\Lodestone\CharacterService;
use App\Service\Lodestone\FreeCompanyService;
use App\Service\Lodestone\PvPTeamService;
use App\Service\Lodestone\ServiceQueues;
use App\Service\LodestoneQueue\CharacterAchievementQueue;
use App\Service\LodestoneQueue\CharacterFriendQueue;
use App\Service\LodestoneQueue\CharacterQueue;
use App\Common\Service\Redis\Redis;
use Lodestone\Api;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\Routing\Annotation\Route;

class LodestoneCharacterController extends AbstractController
{
    /** @var CharacterService */
    private $service;
    /** @var FreeCompanyService */
    private $fcService;
    /** @var PvPTeamService */
    private $pvpService;

    public function __construct(CharacterService $service, FreeCompanyService $fcService, PvPTeamService $pvpService)
    {
        $this->service    = $service;
        $this->fcService  = $fcService;
        $this->pvpService = $pvpService;
    }

    /**
     * @Route("/Character/Search")
     * @Route("/character/search")
     */
    public function search(Request $request)
    {
        if (empty(trim($request->get('name')))) {
            throw new NotAcceptableHttpException('You must provide a name to search.');
        }
        
        return $this->json(
            (new Api())->searchCharacter(
                $request->get('name'),
                ucwords($request->get('server')),
                $request->get('page') ?: 1
            )
        );
    }

    /**
     * @Route("/Character/{lodestoneId}")
     * @Route("/character/{lodestoneId}")
     */
    public function index(Request $request, $lodestoneId)
    {
        $lodestoneId = (int)strtolower(trim($lodestoneId));

        // initialise api
        $api = new Api();

        // choose which content you want
        $data = $request->get('data') ? explode(',', strtoupper($request->get('data'))) : [];
        $content = (object)[
            'AC'  => in_array('AC', $data),
            'FR'  => in_array('FR', $data),
            'FC'  => in_array('FC', $data),
            'FCM' => in_array('FCM', $data),
            'PVP' => in_array('PVP', $data),
        ];

        // response model
        $response = (Object)[
            'Character'          => $api->character()->get($lodestoneId),
            'Achievements'       => null,
            'Friends'            => null,
            'FreeCompany'        => null,
            'FreeCompanyMembers' => null,
            'PvPTeam'            => null,
        ];


        if ($content->AC) {
            $api->config()->useAsync();

            $api->character()->achievements($lodestoneId, 1);
            $api->character()->achievements($lodestoneId, 2);
            $api->character()->achievements($lodestoneId, 3);
            $api->character()->achievements($lodestoneId, 4);
            $api->character()->achievements($lodestoneId, 5);
            $api->character()->achievements($lodestoneId, 6);
            $api->character()->achievements($lodestoneId, 8);
            $api->character()->achievements($lodestoneId, 11);
            $api->character()->achievements($lodestoneId, 12);
            $api->character()->achievements($lodestoneId, 13);

            $response->Achievements = $api->http()->settle();
            $api->config()->useSync();
        }


        /*
        // friends
        if ($content->FR) {
            $friends = $this->service->getFriends($lodestoneId);
            $response->Friends = $friends->data;
            $response->Info->Friends = $friends->ent->getInfo();
        }
        
        // free company
        if (isset($character->data->FreeCompanyId)) {
            if ($content->FC) {
                $freecompany = $this->fcService->get($character->data->FreeCompanyId);
                $response->FreeCompany = $freecompany->data;
                $response->Info->FreeCompany = $freecompany->ent->getInfo();
            }
            
            if ($content->FCM) {
                $members = $this->fcService->getMembers($character->data->FreeCompanyId);
                $response->FreeCompanyMembers = $members->data;
                $response->Info->FreeCompanyMembers = $members->ent->getInfo();
            }
        }

        // if character is in a PvP Team
        if (isset($character->data->PvPTeamId)) {
            if ($content->PVP) {
                $pvp = $this->pvpService->get($character->data->PvPTeamId);
                $response->PvPTeam = $pvp->data;
                $response->Info->PvPTeam = $pvp->ent->getInfo();
            }
        }
        */

        return $this->json($response);
    }

    /**
     * @Route("/Character/{lodestoneId}/Verification")
     * @Route("/character/{lodestoneId}/verification")
     */
    public function verification(Request $request, $lodestoneId)
    {
        $character = $this->service->get($lodestoneId);
    
        if ($character->ent->isBlackListed()) {
            throw new ContentGoneException(LodestoneConstants::API_BLACKLISTED);
        }
    
        if ($character->ent->isAdding()) {
            throw new ContentGoneException(LodestoneConstants::API_NOT_ADDED);
        }
        
        // check if cached, this is to reduce spam
        if ($data = Redis::Cache()->get(__METHOD__ . $lodestoneId)) {
            return $this->json($data);
        }

        $character = (new Api())->getCharacter($lodestoneId);

        // setup response data
        $data = [
            'ID'   => $character->ID,
            'Bio'  => $character->Bio,
            'Pass' => stripos($character->Bio, $request->get('token')) > -1
        ];

        // small cache time as it's just to prevent "spam"
        Redis::Cache()->set(__METHOD__ . $lodestoneId, $data, 5);
        return $this->json($data);
    }

    /**
     * @Route("/Character/{lodestoneId}/Update")
     * @Route("/character/{lodestoneId}/update")
     */
    public function update($lodestoneId)
    {
        $character = $this->service->get($lodestoneId);
    
        if ($character->ent->isBlackListed()) {
            throw new ContentGoneException(LodestoneConstants::API_BLACKLISTED);
        }
    
        if ($character->ent->isAdding()) {
            throw new ContentGoneException(LodestoneConstants::API_NOT_ADDED);
        }

        if ($lodestoneId != 730968 && Redis::Cache()->get(__METHOD__.$lodestoneId)) {
            return $this->json(0);
        }
    
        // send a request to rabbit mq to add this character
        CharacterQueue::request($lodestoneId, 'character_update');
        CharacterFriendQueue::request($lodestoneId, 'character_friends_update');
        CharacterAchievementQueue::request($lodestoneId, 'character_achievements_update');

        Redis::Cache()->set(__METHOD__.$lodestoneId, 1, ServiceQueues::UPDATE_TIMEOUT);
        return $this->json(1);
    }
}
